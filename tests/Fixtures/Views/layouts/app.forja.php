<!doctype html>
<title>@yield('title', 'Forja')</title>
<nav>@include('partials.nav', ['active' => $page ?? 'home'])</nav>
<main>@yield('content')</main>
