<?php

namespace JaOcero\ConversationalForms\Questions;

/**
 * Contact details on one screen: first and last name, email, phone and
 * company. Saved as `['first_name' => …, 'last_name' => …, …]`.
 */
class ContactInfo extends CompositeQuestion
{
    public function getAvailableParts(): array
    {
        return [
            'first_name' => ['label' => __('conversational-forms::conversational-forms.parts.first_name'), 'autocomplete' => 'given-name', 'width' => 'half'],
            'last_name' => ['label' => __('conversational-forms::conversational-forms.parts.last_name'), 'autocomplete' => 'family-name', 'width' => 'half'],
            'email' => ['label' => __('conversational-forms::conversational-forms.parts.email'), 'type' => 'email', 'autocomplete' => 'email', 'inputmode' => 'email', 'rules' => ['email']],
            'phone' => ['label' => __('conversational-forms::conversational-forms.parts.phone'), 'type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel', 'rules' => ['regex:' . Phone::PATTERN]],
            'company' => ['label' => __('conversational-forms::conversational-forms.parts.company'), 'autocomplete' => 'organization'],
        ];
    }

    protected function getDefaultRequiredParts(): array
    {
        return ['first_name', 'email'];
    }

    public function formatAnswer(mixed $answer): string
    {
        if (! is_array($answer)) {
            return '';
        }

        $name = trim(($answer['first_name'] ?? '') . ' ' . ($answer['last_name'] ?? ''));

        return implode(', ', array_filter([
            $name,
            $answer['email'] ?? null,
            $answer['phone'] ?? null,
            $answer['company'] ?? null,
        ], filled(...)));
    }
}
