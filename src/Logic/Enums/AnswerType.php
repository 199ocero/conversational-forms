<?php

namespace JaOcero\ConversationalForms\Logic\Enums;

/**
 * The kind of value a question saves, which decides the operators that make
 * sense for it (see `Operator::appliesTo()`). Variables are numbers.
 */
enum AnswerType: string
{
    case Text = 'text';

    /** One option, such as a single-choice `MultipleChoice` or a `Dropdown`. */
    case Choice = 'choice';

    /** Several options, such as a multiple-choice `MultipleChoice`. */
    case Choices = 'choices';

    case Number = 'number';

    case Date = 'date';

    case Boolean = 'boolean';

    /** Answers only checked for being given: files, signatures, matrices, groups of parts. */
    case Other = 'other';
}
