@extends('errors.layout', [
    'status' => "419",
    'title' => "Your session timed out",
    'lede' => "For your security the page expired while it was open. Nothing you entered was submitted \u2014 please go back and try again.",
    'detail' => "A long-open tab or a browser restart will do this.",
    'reference' => null,
])
