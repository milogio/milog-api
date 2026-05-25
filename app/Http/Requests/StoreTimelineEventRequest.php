<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimelineEventRequest extends FormRequest
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
            'actor_type' => ['required', 'string', 'max:255'],
            'actor_id' => ['required', 'string', 'max:255'],
            'action' => ['required', 'string', 'max:255'],
            'target_type' => ['required', 'string', 'max:255'],
            'target_id' => ['required', 'string', 'max:255'],
            'log_level' => ['nullable', 'string', 'in:trace,debug,info,warn,error,fatal'],
            'metadata' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
