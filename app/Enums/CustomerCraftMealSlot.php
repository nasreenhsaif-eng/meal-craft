<?php

namespace App\Enums;

enum CustomerCraftMealSlot: string
{
    case Breakfast = 'breakfast';
    case Main = 'main';
    case SideSalad = 'side_salad';
    case Dessert = 'dessert';
    case Soup = 'soup';
}
