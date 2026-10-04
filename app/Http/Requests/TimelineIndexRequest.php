<?php

namespace App\Http\Requests;

use App\Support\TimelineLogLevelFilter;
use Illuminate\Foundation\Http\FormRequest;

class TimelineIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'target_id' => ['nullable', 'string', 'max:255'],
            'actor_id' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'log_level' => [
                'sometimes',
                'bail',
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $levels = TimelineLogLevelFilter::parse($value);

                    if (in_array('', $levels, true)) {
                        $fail('The '.$attribute.' field contains an empty value.');

                        return;
                    }

                    if (count($levels) > count(TimelineLogLevelFilter::DISPLAY_TO_RAW)) {
                        $fail('The '.$attribute.' field may not contain more than five distinct values.');

                        return;
                    }

                    if (array_diff($levels, array_keys(TimelineLogLevelFilter::DISPLAY_TO_RAW))) {
                        $fail('The selected '.$attribute.' is invalid.');
                    }
                },
            ],
            'page' => ['nullable', 'integer', 'min:1'],
            'pagination' => ['nullable', 'string', 'in:offset,cursor'],
            'cursor' => ['nullable', 'string'],
        ];
    }
}
