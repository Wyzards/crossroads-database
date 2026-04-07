<?php

namespace App;

enum Accessibility: string
{
    case PUBLIC = 'public';
    case PAID = 'paid';
    case EXCLUSIVE = 'exclusive';
    case CLOSED = 'closed';
}
