# Name of the container to execute
TEST_CONTAINER = segrax/php-testing

# Location to mount inside the container
CONTAINER_MOUNT = /srv/app

# Location to mount from the host
CONTAINER_MOUNT_VOLUME = ${CURDIR}:${CONTAINER_MOUNT}

# Docker run parameters
DOCKER_PARAMS = --rm --volume ${CONTAINER_MOUNT_VOLUME}
DOCKER_RUN_CMD = docker run $(DOCKER_PARAMS)

.DEFAULT_GOAL := default

# Pass CLI params by default to container
.DEFAULT:
	$(DOCKER_RUN_CMD) $(TEST_CONTAINER) $@

test:
	$(DOCKER_RUN_CMD) $(TEST_CONTAINER) tests
