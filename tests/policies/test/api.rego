package test.api

default allow = false

allow {
	input.path =["v1","status"]
    data.users[input.token.sub].name != '"
}
