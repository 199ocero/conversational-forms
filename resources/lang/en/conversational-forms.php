<?php

return [

    'navigation' => [
        'ok' => 'OK',
        'submit' => 'Submit',
        'continue' => 'Continue',
        'next' => 'Next question',
        'previous' => 'Previous question',
        'press_enter' => 'press <kbd>Enter ↵</kbd>',
        'progress' => 'Question :current of :total',
        'progress_label' => 'Progress',
        'completed' => 'Completed',
    ],

    'welcome_screen' => [
        'start' => 'Start',
    ],

    'end_screen' => [
        'title' => 'Thanks for completing this form!',
    ],

    'questions' => [
        'placeholder' => 'Type your answer here…',
        'email_placeholder' => 'name@example.com',
        'long_text_hint' => '<kbd>Shift ⇧</kbd> + <kbd>Enter ↵</kbd> to make a line break',
        'long_text_hint_without_submit' => '<kbd>Ctrl</kbd> / <kbd>⌘</kbd> + <kbd>Enter ↵</kbd> to continue',
        'choose_as_many' => 'Choose as many as you like',
        'choose_between' => 'Choose between :min and :max',
        'choose_at_least' => 'Choose at least :min',
        'choose_up_to' => 'Choose up to :max',
        'other' => 'Other',
        'other_placeholder' => 'Type your answer…',
        'validation_attribute' => 'answer',
        'phone_placeholder' => '+1 555 123 4567',
        'number_placeholder' => 'Type a number…',
        'dropdown_placeholder' => 'Type or select an option',
        'dropdown_empty' => 'No matching options',
        'accept' => 'I accept',
        'must_accept' => 'Please accept to continue.',
        'decline' => "I don't accept",
        'rating_value' => '{1} :value star out of :max|[2,*] :value stars out of :max',
        'nps_start' => 'Not likely at all',
        'nps_end' => 'Extremely likely',
        'day' => 'Day',
        'month' => 'Month',
        'year' => 'Year',
        'hour' => 'Hour',
        'minute' => 'Minute',
        'meridiem' => 'AM / PM',
        'open_calendar' => 'Choose a date from a calendar',
        'calendar' => 'Calendar',
        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'invalid_date' => 'Please enter a valid date.',
        'date_too_early' => 'Please choose a date on or after :date.',
        'date_too_late' => 'Please choose a date on or before :date.',
        'invalid_time' => 'Please enter a valid time.',
        'invalid_ranking' => 'Please keep every item in the list.',
        'yes' => 'Yes',
        'no' => 'No',
    ],

    'parts' => [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'phone' => 'Phone number',
        'company' => 'Company',
        'line1' => 'Address',
        'line2' => 'Address line 2',
        'city' => 'City',
        'state' => 'State / Province',
        'postal_code' => 'Postal code',
        'country' => 'Country',
    ],

    'ranking' => [
        'instructions' => 'Drag the items into order, or press Space to pick one up, ↑ / ↓ to move it and Space to drop it.',
        'grabbed' => ':item picked up, position :position of :total. Use the arrow keys to move it, Space to drop it, Escape to cancel.',
        'moved' => ':item moved to position :position of :total.',
        'dropped' => ':item dropped at position :position of :total.',
        'cancelled' => 'Move cancelled. :item is back at position :position of :total.',
        'move_up' => 'Move :item up',
        'move_down' => 'Move :item down',
    ],

    'upload' => [
        'choose_file' => 'Choose a file or drag it here',
        'choose_files' => 'Choose files or drag them here',
        'max_size' => 'Up to :size',
        'uploaded' => ':name uploaded.',
        'failed' => ':name could not be uploaded. Please try again.',
        'removed' => ':name removed.',
        'remove' => 'Remove :name',
        'wrong_type' => ':name is not an accepted file type.',
        'too_large' => ':name is too large.',
        'too_many' => 'You cannot add more files.',
        'types' => [
            'image' => 'Images',
            'video' => 'Videos',
            'audio' => 'Audio',
            'text' => 'Text files',
            'application' => 'Files',
        ],
    ],

    'signature' => [
        'draw_here' => 'Sign here',
        'clear' => 'Clear',
        'type_instead' => 'Type your name instead',
        'draw_instead' => 'Draw instead',
        'type_label' => 'Your full name',
        'type_placeholder' => 'Type your full name',
        'signed' => 'Signed',
        'empty' => 'Not signed yet',
        'invalid' => 'Please sign again.',
    ],

    'nps' => [
        'detractor' => 'Detractor',
        'passive' => 'Passive',
        'promoter' => 'Promoter',
    ],

    'hints' => [
        'choose' => 'Choose an answer',
    ],

];
