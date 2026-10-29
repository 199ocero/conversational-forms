<?php

namespace JaOcero\ConversationalForms\Logic\Enums;

enum CalculationOperator: string
{
    case Add = 'add';
    case Subtract = 'subtract';
    case Multiply = 'multiply';
    case Divide = 'divide';
    case Set = 'set';
}
