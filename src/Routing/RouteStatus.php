<?php

declare(strict_types=1);

namespace Forja\Routing;

enum RouteStatus
{
    case Found;
    case NotFound;
    case MethodNotAllowed;
}
