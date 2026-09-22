<?php

namespace App\Support\ListingPreferences\Taste;

/**
 * Which way the customer's own evidence points.
 *
 *   positive   mostly Saves
 *   negative   mostly Passes
 *   mixed      Saves and Passes in comparable measure — a conflict, said so
 *   uncertain  mostly Maybes — interest without commitment
 */
enum TasteDirection: string
{
    case Positive  = 'positive';
    case Negative  = 'negative';
    case Mixed     = 'mixed';
    case Uncertain = 'uncertain';
}
