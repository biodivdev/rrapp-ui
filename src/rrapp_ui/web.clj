(ns rrapp-ui.web
  (:require [rrapp-ui.core :as core])
  (:require [rrapp-ui.views :as views])

  (:require [clojure.java.io :as io])
  (:require [taoensso.timbre :as log])
  (:require [environ.core :refer [env]])

  (:require [immutant.web :as web])

  (:use ring.middleware.params)
  (:use ring.middleware.keyword-params)
  (:use ring.middleware.resource)
  (:use ring.middleware.content-type)
  (:use ring.middleware.not-modified)
  (:use ring.middleware.content-type)
  (:use ring.middleware.reload)
  (:use ring.middleware.gzip)
  (:use ring.middleware.cors)

  (:use ring.util.io)
  (:require [ring.util.response :refer [not-found redirect resource-response]])

  (:require [compojure.core :refer :all]
            [compojure.route :as route])

  (:require [clojure.data.json :as json])

  (:gen-class))

(defroutes router
  (GET "/" [] (views/index))
  (GET "/families" [] (views/families))
  (GET "/family/:family"  {{family :family} :params} (views/family family))
  (GET "/search" {{query :query} :params} (views/search query))
  (GET "/taxon/:taxon" {{taxon :taxon} :params} (views/taxon taxon))

  (GET "/about" [] (views/about))

  (GET "/api/taxon/:taxon/analysis" {{taxon :taxon} :params} (json/write-str (core/get-spp taxon)))
  (GET "/api/taxon/:taxon/occurrences" {{taxon :taxon} :params} (json/write-str (core/get-occs (core/get-spp taxon))))


  (route/not-found "Not found"))

(def app
  (-> #'router
    (wrap-gzip)
    (wrap-resource "public")
    (wrap-content-type)
    (wrap-not-modified)
    (wrap-cors #".*")
    (wrap-keyword-params)
    (wrap-params)
    (wrap-reload)))

(defn -main
  [ & args] 
  (let
    [host (or (env :host) "0.0.0.0")
     port (or (env :port) "8080")]
    (log/info (str "Listening on " host ":" port))
    (web/run #'app {:port (Integer/valueOf port) :host host})))

