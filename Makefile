all: build

build: jar docker

jar:
	lein uberjar

docker:
	docker build -t diogok/rrapp-ui .

push:
	docker push diogok/rrapp-ui
