all: build

build:
	docker build -t diogok/biodiv-ui .

push:
	docker push diogok/biodiv-ui
