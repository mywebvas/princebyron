@extends('errors.layout', [
    'status' => "404",
    'title' => "This page isn't here",
    'lede' => "The link may be old, mistyped, or the page may have moved. Nothing is broken on your side.",
    'detail' => null,
    'reference' => null,
])
