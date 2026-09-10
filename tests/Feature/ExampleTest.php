<?php

test('the application root redirects to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

test('login page returns a successful response', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});
