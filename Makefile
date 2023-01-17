export DOCKER_BUILDKIT=0
export COMPOSE_DOCKER_CLI_BUILD=0

ROOT_DIR 	   := $(abspath $(lastword $(MAKEFILE_LIST)))
PROJECT_DIR	 := $(notdir $(patsubst %/,%,$(dir $(ROOT_DIR))))
PROJECT 		 := $(lastword $(PROJECT_DIR))
VERSION_FILE 	= VERSION
VERSION			 	= `cat $(VERSION_FILE)`
SRC_VOLUME 		= "${PWD}"

default: run

bloop:
	@echo "${SRC_VOLUME}"

.PHONY: help
help: ## Print all the available commands
	@echo "" \
	&& echo "Alloy ${VERSION}" \
	&& echo "" \
	&& grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' \
	&& echo ""
	
build: ## Build the Alloy environment
	@echo \
	&& echo "Building environment..." \
	&& docker build --rm --tag app:${VERSION} .

run: build ## Run live environment
	@echo \
	&& echo "Connecting to environment" \
	&& docker run -it --privileged=true --network=host --rm --volume ${SRC_VOLUME}:/app  app:${VERSION}

release:  ## Build the project in release mode
	@echo "Release"

setup:  ## Setup for development
	@echo "Setup Env"