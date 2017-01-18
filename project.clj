(defproject rrapp-ui "0.0.1"
  :description "Web User Interface for RRAPP."
  :url "https://github.com/biodivdev/rrapp-ui"
  :license {:name "TMIT"}
  :main rrapp-ui.web
  :dependencies [[org.clojure/clojure "1.8.0"]

                 [clj-http-lite "0.3.0"]
                 [org.clojure/data.json "0.2.6"]
                 [org.clojure/core.cache "0.6.5"]

                 [org.immutant/web "2.1.5"]

                 [ring/ring-core "1.5.0"]
                 [ring/ring-devel "1.5.0"]
                 
                 [jumblerg/ring.middleware.cors "1.0.1"]
                 [amalloy/ring-gzip-middleware "0.1.3"]

                 [hiccup "1.0.5"]

                 [compojure "1.5.1"]

                 [environ "1.1.0"]
                 [com.taoensso/timbre "4.7.4"]])
