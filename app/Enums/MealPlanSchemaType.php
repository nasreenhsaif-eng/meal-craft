<?php

namespace App\Enums;

enum MealPlanSchemaType: string
{
    case Weekly = 'weekly';
    case FourWeek = 'four_week';
    case WeeklyStructured = 'weekly_structured';
}
