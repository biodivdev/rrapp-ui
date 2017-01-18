(ns rrapp-ui.core
  (:use rrapp-ui.utils)
  (:require [taoensso.timbre :as log]))

(def es (str (config :elasticsearch) "/" (config :index)))

(defn get-families
  [] 
  (->> {:size 0
        :aggs 
         {:families
           {:aggs {:families {:terms {:field "family.keyword" :size 999999}}}
            :filter {:bool {:must [{:term {:taxonomicStatus "accepted"}}
                                   {:term {:source (config :source)}}]}}}}}
    (post-json (str es "/taxon/_search"))
    :aggregations
    :families
    :families
    :buckets
    (map :key)
    (sort)))

(defn get-spp
  [spp-name]
  (->> {:size 1
        :query {:term {:scientificNameWithoutAuthorship.keyword spp-name}}}
    (post-json (str es "/analysis/_search"))
    :hits
    :hits
    first
    :_source))

(defn dedupme
  [col]
  (vals
    (reduce 
      (fn [h spp]
        (assoc h (:scientificNameWithoutAuthorship spp) spp))
      {}
      col)))

(defn get-occs-0
  [names] 
  (->> {:size 9999
        :query
         {:bool
          {:should 
            (for [spp-name names]
              {:match 
               {:scientificNameWithoutAuthorship 
                {:query spp-name :type "phrase"}}})}}}
    (post-json (str es "/occurrence/_search"))
    :hits
    :hits
    (map :_source)))

(defn get-occs
  [spp] 
  (get-occs-0 (conj (map :scientificNameWithoutAuthorship (:synonyms spp)) (:scientificNameWithoutAuthorship spp))))

(defn maybe-after
  [after q] 
   (if (nil? after) q (assoc q :search_after [after])))

(defn search-spps-0
  ([q] 
   (loop [since nil acc []]
     (let [r (search-spps-0 q since 5000)]
       (if (< (count r) 5000)
         (concat acc r)
         (recur (:taxonID (last r)) (concat acc r))))))
  ([q since limit] 
    (->>
      {:size limit
       :_source ["scientificNameWithoutAuthorship","scientificNameAuthorship","synonyms.scientificNameWithoutAuthorship","synonyms.scientificNameAuthorship","family","taxonID"]
       :sort ["taxonID"]
       :query {:query_string {:query q :analyze_wildcard false}}}
      (maybe-after since)
      (post-json (str es "/analysis/_search"))
      :hits
      :hits
      (map :_source))))

(defn search-spps
  [q] 
  (->> 
    (search-spps-0 q)
    (dedupme)
    (group-by :family)
    (map #(hash-map :family (key %) :species (sort-by :scientificNameWithoutAuthorship (val %))))
    (sort-by :family)))

(defn get-spps
  [family] 
  (->> 
    {:size 9999
     :_source ["scientificNameWithoutAuthorship","scientificNameAuthorship","synonyms.scientificNameWithoutAuthorship","synonyms.scientificNameAuthorship"]
     :query {:term {:family.keyword family}}}
    (post-json (str es "/analysis/_search"))
    :hits
    :hits
    (map :_source)
    (dedupme)
    (sort-by :scientificNameWithoutAuthorship)))

(defn count-taxa
  ([] (:count (get-json (str es "/analysis/_count"))))
  ([q] 
   (if (nil? q) (count-taxa)
     (->> {:query {:query_string {:query q }  }}
       (post-json (str es "/analysis/_count"))
        :count))))

(defn map-range
  [buckets]
  (map
    (fn [b] {:from (int (:from b)) :to (int (:to b)) :val (:doc_count b)})
    buckets))

(defn re-stats
  [s]
  {:occs-count (int (:value (:occs-count s)))
   :points-count (int (:value (:points-count s)))
   :not-points-count (int (- (:value (:occs-count s)) (:value (:points-count s))))
   :categories 
    (reverse
      (sort-by :val 
        (map
          (fn [b] {:key (:key b) :val (:doc_count b)})
          (:buckets (:categories s)))) )
   :occs-range (map-range (:buckets (:occs-range s)))
   :points-range (map-range (:buckets (:points-range s)))
   :clusters-range (map-range (:buckets (:clusters-range s)))
   :aoo-range (map-range (:buckets (:aoo-range s)))
   :eoo-range (map-range (:buckets (:eoo-range s)))})

(defn stats
  ([] (stats {:match_all {}}))
  ([q] 
   (->>
     {:size 0
      :query q
      :aggs {
        :occs-count {:sum {:field "occurrences.count"}}
        :points-count {:sum {:field "points.count"}}
        :categories {:terms {:field "main-risk-assessment.category" :size 9}}
        :occs-range 
           {:range 
            {:field "occurrences.count"
             :ranges [
               {:from 0 :to 1}
               {:from 1 :to 3}
               {:from 3 :to 10}
               {:from 10 :to 100}
               {:from 100 :to 99999}]}}
        :points-range 
           {:range 
            {:field "points.count"
             :ranges [
               {:from 0 :to 1}
               {:from 1 :to 3}
               {:from 3 :to 10}
               {:from 10 :to 100}
               {:from 100 :to 99999}]}}
        :clusters-range 
           {:range 
            {:field "clusters.all.count"
             :ranges [
               {:from 0 :to 1}
               {:from 1 :to 3}
               {:from 3 :to 10}
               {:from 10 :to 100}
               {:from 100 :to 99999}]}}
        :aoo-range 
           {:range 
            {:field "aoo.all.area"
             :ranges [
               {:from 0 :to 1}
               {:from 1 :to 10}
               {:from 10 :to 50}
               {:from 50 :to 100}
               {:from 100 :to 500}
               {:from 500 :to 2000}
               {:from 2000 :to 5000}
               {:from 5000 :to 99999}]}}
        :eoo-range 
           {:range 
            {:field "eoo.all.area"
             :ranges [
               {:from 0 :to 1}
               {:from 1 :to 100}
               {:from 100 :to 500}
               {:from 500 :to 1000}
               {:from 1000 :to 5000}
               {:from 5000 :to 20000}
               {:from 20000 :to 50000}
               {:from 50000 :to 99999}]}}
       }} 
     (post-json (str es "/analysis/_search"))
     :aggregations
     (re-stats))))

