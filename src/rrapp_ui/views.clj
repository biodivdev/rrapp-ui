(ns rrapp-ui.views
  (:use hiccup.core
        hiccup.page)
  (:require [rrapp-ui.core :as core])
  (:require [environ.core :refer [env]])
  (:require [clojure.data.json :as json]))

(defn get-lang
  [] 
  (apply str (first (partition 2 (or (env :lang) "en")))))

(def strings 
  (merge
    (json/read-str (slurp (clojure.java.io/resource (str "locales/en.json"))) :key-fn keyword)
    (json/read-str (slurp (clojure.java.io/resource (str "locales/" (get-lang) ".json"))) :key-fn keyword)))

(defn localize
  [text] 
  (or (strings text) (name text)))

(defn page
  [title & content] 
   (html5
     [:head
      [:meta {:charset "UTF-8"}]
      [:meta {:name "viewport" :content "width=device-width, initial-scale=1"}]
      [:title (str title " " (localize :title))]
      (include-css "/css/pure.min.css")
      (include-css "/css/pure.grids.responsive.min.css")
      (include-css "/css/app.css")]
     [:body
      [:div.content.pure-g
       [:header.pure-u-1
        [:h1 [:a {:href (str (env :base) "/")} (localize :title)]]
        [:h2 (localize :subtitle)]]
       [:div.pure-u-1
         [:div.content content]]
       [:footer.pure-u-1
        [:p.feedback
         [:a {:href "https://goo.gl/forms/AhEeLUIO9WlnKquc2"}
          (localize :feedback-text)]]
        [:p
         [:a {:href (str (env :base) "/")} (localize :home)]
         " "
         [:a {:href (str (env :base) "/about")} (localize :about)]
         " "
         [:a {:href "https://goo.gl/forms/AhEeLUIO9WlnKquc2"}
          (localize :feedback)]]]]
      (include-js "/js/ga.js")]))

(defn search-form
  [ value ]
    [:form.pure-form.pure-u-1.pure-form-stacked
     {:method "GET" :action (str (env :base) "/search")}
     [:fieldset
      [:legend (localize :search)]
      [:input.pure-input-1 {:type "text" :name "query" :placeholder (localize :make-your-query)} value]
      [:button.pure-button.pure-button-primary {:type "submit"} (localize :search)]]])

(defn q-search
  [current news]
  (apply str
    (interpose " AND "
      (distinct
        (filter
           #(and (not (nil? %)) (not (empty? %)))
          (.split
            (str current " AND " news)
            " AND "))))))

(defn stats
  [q] 
  (let [s (if (nil? q) (core/stats) (core/stats  {:query_string {:query q :analyze_wildcard false}}))]
    [:div
      [:p (localize :containing) " " (core/count-taxa q) " " (localize :accepted-names-with) " " (:occs-count s) " " (localize :occurrences) "."]
      [:p (localize :stats-info)]

    [:section.pure-u-1.cats
      [:h3.pure-u-1 (localize :risk-categories)]
      [:article.pure-u-1.pure-u-lg-1-2
       [:p (localize :risk-categories-desc)]
       [:table.pure-table
        (for [cat (:categories s)]
          [:tr.cat {:data-key (:key cat) :data-val (:val cat)}
             [:th 
               [:a {:href (str (env :base) "/search?query=" (q-search q (str "main-risk-assessment.category:\"" (:key cat) "\"" )))}
                  (:key cat)]]
           [:td (:val cat)]])]]]

      [:section.pure-u-1.occs
        [:h3.pure-u-1 (localize :occurrences-and-points)]
        [:article.pure-u-1.pure-u-lg-1-2
         [:p (localize :occurrences-and-points-desc)]
         [:table.pure-table
          [:tr [:th (localize :occurrences)] [:td (:occs-count s)]]
          [:tr [:th (localize :not-points)] [:td (:not-points-count s)]]
          [:tr [:th (localize :points)] [:td (:points-count s)]]]]]

      [:section.pure-u-1.bar
        [:h3.pure-u-1 (localize :number-of-occs-by-spp)]
        [:article.pure-u-1.pure-u-lg-1-2
         [:p (localize :number-of-occs-by-spp-desc)]
         [:table.pure-table
          (for [rg (:occs-range s)]
            [:tr {:data-from (:from rg) :data-to (:to rg) :data-val (:val rg)}
               [:th  
                 [:a {:href (str (env :base) "/search?query=" (q-search q (str "occurrences.count:(>=" (:from rg) " AND <" (:to rg)")" )))}
                    (str (:from rg) " ~ " (:to rg))]]
             [:td (:val rg)]])]]]

      [:section.pure-u-1.bar
        [:h3.pure-u-1 (localize :number-of-points-by-spp)]
        [:article.pure-u-1.pure-u-lg-1-2
         [:p (localize :number-of-points-by-spp-desc)]
         [:table.pure-table
          (for [rg (:points-range s)]
            [:tr {:data-from (:from rg) :data-to (:to rg) :data-val (:val rg)}
               [:th                   [:a {:href (str (env :base) "/search?query=" (q-search q (str "points.count:(>=" (:from rg) " AND <" (:to rg)")" )))}
                    (str (:from rg) " ~ " (:to rg))]]
             [:td (:val rg)]])]]]

      [:section.pure-u-1.bar
        [:h3.pure-u-1 (localize :number-of-clusters-by-spp)]
        [:article.pure-u-1.pure-u-lg-1-2
         [:p (localize :number-of-clusters-by-spp-desc)]
         [:table.pure-table
          (for [rg (:clusters-range s)]
            [:tr {:data-from (:from rg) :data-to (:to rg) :data-val (:val rg)}
               [:th 
                 [:a {:href (str (env :base) "/search?query=" (q-search q (str "clusters.all.count:(>=" (:from rg) " AND <" (:to rg)")" )))}
                    (str (:from rg) " ~ " (:to rg))]]
             [:td (:val rg)]])]]]

      [:section.pure-u-1.bar
        [:h3.pure-u-1 (localize :aoo)]
        [:article.pure-u-1.pure-u-lg-1-2
         [:p (localize :aoo-desc)]
         [:table.pure-table
          (for [rg (:aoo-range s)]
            [:tr {:data-from (:from rg) :data-to (:to rg) :data-val (:val rg)}
               [:th 
                 [:a {:href (str (env :base) "/search?query=" (q-search q (str "aoo.all.area:(>=" (:from rg) " AND <" (:to rg)")" )))}
                    (str (:from rg) " ~ " (:to rg))]]
             [:td (:val rg)]])]]]

      [:section.pure-u-1.bar
        [:h3.pure-u-1 (localize :eoo)]
        [:article.pure-u-1.pure-u-lg-1-2
         [:p (localize :eoo-desc)]
         [:table.pure-table
          (for [rg (:eoo-range s)]
            [:tr {:data-from (:from rg) :data-to (:to rg) :data-val (:val rg)}
               [:th 
                 [:a {:href (str (env :base) "/search?query=" (q-search q (str "eoo.all.area:(>=" (:from rg) " AND <" (:to rg)")" )))}
                    (str (:from rg) " ~ " (:to rg))]]
             [:td (:val rg)]])]]]
     (include-js "/js/chart.min.js")
     (include-js "/js/stats.js")
     ]))

(defn index
  [] 
  (page "" 
    (search-form nil)
    [:p (localize :or) " " [:a {:href (str (env :base) "/families")} (localize :navigate-by-families)] "."]
    [:h2 (localize :statistics)]
    (stats nil)))

(defn families
  [] 
   (page (localize :families)
     [:h1.pure-u-1 (localize :families)]
     [:ul.pure-u-1
       (for [family (core/get-families)]
         [:li [:a {:href (str (env :base) "/family/" family)} family]])]))

(defn family
  [family]
  (page family
    [:h2 (localize :stats-of)  " " family]
    [:a {:href "#species"} (localize :scroll-to-list)]
    (stats (str "family.keyword:\"" family "\""))
    [:a {:name "#species"}]
    [:h3 (localize :species-of) " " family]
    [:ul
     (for [spp (core/get-spps family)]
       [:li 
        [:a {:href (str (env :base) "/taxon/" (:scientificNameWithoutAuthorship spp))}
         [:i (:scientificNameWithoutAuthorship spp)]]
          " " (:scientificNameAuthorship spp)
        [:br]
        [:small 
         (for [syn (:synonyms spp)]
          [:span
           [:i (:scientificNameWithoutAuthorship syn)]
           " "
           (:scientificNameAuthorship syn)
           "; "])]
        ])]))

(defn search
  [q]
  (page (localize :search)
    [:h2 (localize :stats-of) " " (localize :search)]
    [:a {:href "#species"} (localize :scroll-to-list)]
    (stats q)
    [:a {:name "#species"}]
    [:h3 (localize :species-of) " " (localize :search)]
    [:ul
    (for [f (core/search-spps q)]
       [:li 
         [:p [:strong (:family f)]]
         [:ul
         (for [spp (:species f)]
           [:li 
            [:a {:href (str (env :base) "/taxon/" (:scientificNameWithoutAuthorship spp))}
             [:i (:scientificNameWithoutAuthorship spp)]]
              " " (:scientificNameAuthorship spp)
            [:br]
            [:small 
             (for [syn (:synonyms spp)]
              [:span
                [:i (:scientificNameWithoutAuthorship syn)]
               " "
               (:scientificNameAuthorship syn) 
               "; "])]])]])]))

(defn taxon-table
  [spp cut]
  [:table.pure-table
   [:tr 
    [:th (localize :eoo)]
    [:td (format "%.2f" (float (get-in spp [:eoo cut :area]) ) ) "km²"]] 
   [:tr 
    [:th (localize :aoo) " (" (get-in spp [:aoo cut :cell_size]) "km²)"]
    [:td (get-in spp [:aoo cut :area]) "km²"]]
   [:tr 
    [:th (localize :aoo) " (" (format "%.2f" (float (get-in spp [:aoo-variadic cut :cell_size]) )) "km²)"]
    [:td (format "%.2f" (float (get-in spp [:aoo-variadic cut :area]) )) "km²"]]
   [:tr 
    [:th (localize :clusters)]
    [:td (get-in spp [:clusters cut :count])]]
   [:tr 
    [:th (localize :clusters)]
    [:td (format "%.2f" (float (get-in spp [:clusters cut :area]) )) "km²"]]
   ])

(defn taxon
  [spp-name]
  (let [spp (core/get-spp spp-name)]
    (page (:scientificName spp)

      [:section
        [:h2 (:family spp)]
        [:h1 [:i (:scientificNameWithoutAuthorship spp)] " " (:scientificNameAuthorship spp)]
        [:p "Synonyms: "
         (if (empty? (:synonyms spp))
           "N/A"
           (for [syn (:synonyms spp)]
             [:span 
              [:i (:scientificNameWithoutAuthorship syn)]
              " " (:scientificNameAuthorship syn) "; "]))]]

      [:section
        [:h3 (localize :risk-assessment)]
        [:table.pure-table
         [:tr 
          [:th (localize :category)] 
          [:th (localize :criteria)]
          [:th (localize :reason)]]
         (for [a (:risk-assessment spp)]
           [:tr
            [:td (:category a)]
            [:td (:criteria a)]
            [:td (:reason a)]])]]

       [:section
         [:h3 (localize :occurrences-and-points)]
         [:p (localize :occurrences-and-points-desc)]
         [:table.pure-table
          [:tr 
           [:th (localize :total)]
           [:td (get-in spp [:occurrences :count]) "/" (get-in spp [:points :count])]]
          [:tr 
           [:th (localize :recent)]
           [:td (get-in spp [:occurrences :count_recent]) "/" (get-in spp [:points :count_recent])]]
          [:tr 
           [:th (localize :historic)]
           [:td (get-in spp [:occurrences :count_historic]) "/" (get-in spp [:points :count_historic])]]]]

        [:section
          [:h3 (localize :all-occs)]
          (taxon-table spp :all)]

        [:section
          [:h3 (localize :recent-only)]
          (taxon-table spp :recent)]

        [:section
          [:h3 (localize :historic-only)]
          (taxon-table spp :historic)]

        [:section.occurrences
         [:h3 (localize :occurrences)]
         (for [occ (core/get-occs spp)]
           [:table.pure-table.pure-u-1.occ
            (for [kv occ]
              [:tr [:th (key kv)] [:td (or (val kv) "")]])])]

        (include-css "/leaflet/leaflet.css")
        (include-css "/leaflet/markercluster.css")
        (include-css "/leaflet/markercluster.default.css")
        (include-js "/leaflet/leaflet.js")
        (include-js "/leaflet/leaflet.markercluster.js")
        (include-js "/js/map.js")
      )))

(defn about []
    (page "About"
      [:section {:class "pure-u-1"}
       [:article  
        [:h3 "About this tool"]
        [:p "This is a\n        set of " 
         [:em "open-source"]" tools\n        to " 
         [:em "daily"]"collect and serve consolidated " 
         [:em "taxonomic"]" and " 
         [:em "occurrence"]" data\n        from various " 
         [:em "open data"]" sources\n        , perform " 
         [:em "geospatial"]" analysis\n        that enable rapid " 
         [:em "risk assessment"]"of country-level biodiversity\n        , and allow simple and directed " 
         [:em "visualization"]"of the data and results."]]
       [:article  
        [:h3 "What made this possible"]
        [:p "All this work is possible due to a series of developments in contribution, publishing and technologies related to biodiversity."]
        [:p "Namely, not limited to:"]
        [:ul  
         [:li "DarwinCore standart by TDWG, that set common terms to work upon"]
         [:li "DarwinCore-Archive design by GBIF, that allowed the exchange in efficient format of information"]
         [:li "Integrated Publishing Toolkit from GBIF, that made simple and easy access to datasets"]
         [:li "GBIF push and committiment to help publishers, which allows easy dicovery of source of data"]
         [:li "IUCN Red Listing Guidelines, that provide a base set of rules for extinction risk assessment"]]]
       [:article  
        [:h3 "How does this work"]
        [:p "Specifically, the daily workflow goes as follows, mostly in parallel:"]
        [:ul  
         [:li "Collect country taxonomic information from choosen checklists published in IPT"]
         [:li "
          [TODO]Download and load into local IPT country occurrences from GBIF by families"]
         [:li "Collect from local and choosen IPTs occurrence datasets"]
         [:li "For each taxon:" 
          [:ul  
           [:li "Consolidate occurrences from accepted name and synonyms"]
           [:li "Classify occurrences in groups" 
            [:ul  
             [:li "All"]
             [:li "Historic"]
             [:li "Recent"]]]
           [:li "
            [TODO]Rate the quality of occurrences"]
           [:li "For each group of occurrences perform the following analysis" 
            [:ul  
             [:li "Extent of occurrence (EOO)"]
             [:li "Area of occupancy with regular grid of 2km side (AOO-2km)"]
             [:li "Area of occupancy withh grid of variadic size (AOO-variadic)"]
             [:li "Subpopulations/Locations/Cluster of occurrences based on circular buffer of median distance"]
             [:li "Rapid Risk Assessment based on EOO, AOO and decline only"]]]]]
         [:li "Display the results and statistics."]]
        [:h4 "Extent of occurrences (EOO)"]
        [:p "Utilizes a convex-hull method to calculate the total extent of occurrence for the specie."]
        [:h4 "Area of Occupancy (AOO)"]
        [:p "Using a world grid of 2km of side and of 10% of the maximum distance between occurrences, and matching those which have occurrences on it, \n           distincts them to calculate the area of occupancy of the specie."]
        [:h4 "Population clusters"]
        [:p "Based on 10% of the maximum distance between the species occurrences (to be changed to a minimum-spaning-tree following Rapoport&#39;s approach), draws a buffer of this radius around them\n           and groups those that intersects."]
        [:h4 "Risk Assessment Analysis"]
        [:p "Using IUCN category and criteria extinction risk assessment, but based only on geographical distribution and futher simplified."]
        [:table {:class "pure-table"}
         [:thead  
          [:tr  
           [:th "Metric"]
           [:th "Value"]
           [:th "Category (criteria)"]]]
         [:tbody  
          [:tr  
           [:td "Number of records"]
           [:td " &lt; 3"]
           [:td "DD"]]
          [:tr  
           [:td "Area of occupancy"]
           [:td " &lt; 10km²"]
           [:td "CR (B2)"]]
          [:tr  
           [:td "Area of occupancy"]
           [:td " &lt; 500km²"]
           [:td "EN (B2)"]]
          [:tr  
           [:td "Area of occupancy"]
           [:td " &lt; 2000km²"]
           [:td "VU (B2)"]]
          [:tr  
           [:td "Extent of occurrence"]
           [:td " &lt; 100km²"]
           [:td "CR (B1)"]]
          [:tr  
           [:td "Extent of occurrence"]
           [:td " &lt; 5000km²"]
           [:td "EN (B1)"]]
          [:tr  
           [:td "Extent of occurrence"]
           [:td " &lt; 20000km²"]
           [:td "VU (B1)"]]]]
        [:p "Decline and number of subpopulation might be taken into account as official guidelines suggest, if available."]
        [:p " Due to lack of a method for some extra information (specially locations and threats), the categories had to be simplified.\n            The methodology can be improved if we have easy access to such data. "]
        [:img {:src "https://www.lucidchart.com/publicSegments/view/e7bb93d5-57c3-496e-81e0-a8dbf615b2c4/image.png", :alt "Biodiversity Indexer Workflow", :class "workflow"}]]
       [:article  
        [:h3 "Other informations and links"]
        [:p "You can read the " 
         [:a {:href "http://www.lbd.dcc.ufmg.br/colecoes/wcama/2016/003.pdf"} "paper published at CSBC 2016 (WCAMA)"]", entitled &quot;Assessing the risk of extinction of Brazil’s flora: A computational approach based on micro-services and geospatial analysis&quot;, that describes the tools and methodolgies."]
        [:p "Follow the progress and participate on the " 
         [:a {:href "https://trello.com/b/5El2zEJK/biodiv"} "public task manager"]"."]
        [:p "You can also check the " 
         [:a {:href "https://github.com/biodivdev/rrapp-compose"} "source code"]" of the open source tools at github."]
        [:p "Any feedback you can get in contact by email: " 
         [:em "diogo@diogok.net"]"."]
        [:p "All source code is available at gihub:"]
        [:ul  
         [:li 
          [:a {:href "https://github.com/biodivdev/rrapp-compose"} "Composition of all apps"]]
         [:li 
          [:a {:href "https://github.com/biodivdev/rrapp-ui"} "Web interface"]]
         [:li 
          [:a {:href "https://github.com/biodivdev/rrapp-idx"} "General indexer"]]
         [:li 
          [:a {:href "https://github.com/biodivdev/dwc-bot-es"} "Occurrence bot to ElasticSearch"]]]]]
))
