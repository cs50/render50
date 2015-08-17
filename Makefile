default: build run

pull:
	docker login
	docker pull cs50/render50

build:
	docker build -t cs50/render50 .

rebuild:
	docker build --no-cache -t cs50/render50 .

run:
	docker run -i --rm -v `pwd`:/root -t cs50/render50
