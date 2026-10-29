<?php

namespace JaOcero\ConversationalForms\Questions;

/**
 * A postal address on one screen. Saved as `['line1' => …, 'city' => …, …]`.
 */
class Address extends CompositeQuestion
{
    public function getAvailableParts(): array
    {
        return [
            'line1' => ['label' => __('conversational-forms::conversational-forms.parts.line1'), 'autocomplete' => 'address-line1'],
            'line2' => ['label' => __('conversational-forms::conversational-forms.parts.line2'), 'autocomplete' => 'address-line2'],
            'city' => ['label' => __('conversational-forms::conversational-forms.parts.city'), 'autocomplete' => 'address-level2', 'width' => 'half'],
            'state' => ['label' => __('conversational-forms::conversational-forms.parts.state'), 'autocomplete' => 'address-level1', 'width' => 'half'],
            'postal_code' => ['label' => __('conversational-forms::conversational-forms.parts.postal_code'), 'autocomplete' => 'postal-code', 'width' => 'half'],
            'country' => ['label' => __('conversational-forms::conversational-forms.parts.country'), 'autocomplete' => 'country-name', 'width' => 'half'],
        ];
    }

    protected function getDefaultRequiredParts(): array
    {
        return ['line1', 'city', 'postal_code', 'country'];
    }
}
