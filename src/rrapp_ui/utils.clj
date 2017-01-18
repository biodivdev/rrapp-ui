(ns rrapp-ui.utils
  (:require [clojure.java.io :as io])
  (:require [clojure.core.cache :as cache])
  (:require [clj-http.lite.client :as client])
  (:require [taoensso.timbre :as log])
  (:require [environ.core :refer [env]])
  (:require [clojure.data.json :as json]))

(def config-file
  (let [env   (io/file (or (env :config) "/etc/biodiv/config.ini"))
        base  (io/resource "config.ini")]
    (if (.exists env)
      env
      base)))

(def c (atom (cache/ttl-cache-factory {} :ttl (* 2 24 60 60 1000))))

(def config-
  (with-open [rdr (io/reader config-file)]
    (->> (line-seq rdr)
         (map #(.trim %))
         (filter #(and (not (nil? %)) (not (empty? %))))
         (map (fn [line] ( .split line "=" )))
         (map (fn [pair] [(keyword (.toLowerCase (.trim (first pair)))) (.trim (last pair))]))
         (map (fn [kv] {(first kv) (or (env (first kv)) (last kv))}))
         (reduce merge {}))))

(defn config 
  [k] (or (env k) (config- k)))

(defn get-json
  [& url] 
  (log/info "Get JSON" (apply str url))
  (try
    (json/read-str (:body (client/get (apply str url))) :key-fn keyword)
    (catch Exception e 
      (do (log/error (str "Failled get JSON " (apply str url)))
          (log/error e)))))

(defn post-json
  [url body] 
  (log/info "POST JSON" url)
  (try
    (-> 
      (client/post url {:content-type :json :body (json/write-str body)})
      :body
      (json/read-str :key-fn keyword))
    (catch Exception e 
      (do (log/error (str "Failed POST JSON " (apply str url)) body)
          (log/error e)))))

(defn post-json-cached
  [url body]
  (let [id (hash (str url body))]
    (if (cache/has? @c id)
      (cache/lookup @c id)
      (let [v (post-json url body)]
        (swap! c #(assoc % id v))
        v))))

