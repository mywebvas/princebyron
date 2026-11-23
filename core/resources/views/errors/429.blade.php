@extends('errors.layout', [
    'status' => "429",
    'title' => "Slow down a moment",
    'lede' => "There have been a lot of requests from this connection in a short time. Please wait a minute and try again.",
    'detail' => "If you are on a shared network, someone else may be the cause.",
    'reference' => null,
])
