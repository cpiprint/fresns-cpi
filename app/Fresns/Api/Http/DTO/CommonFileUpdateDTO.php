<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace App\Fresns\Api\Http\DTO;

use Fresns\DTO\DTO;

class CommonFileUpdateDTO extends DTO
{
    public function rules(): array
    {
        return [
            'uploaded' => ['boolean', 'nullable'],
            'warning' => ['string', 'nullable', 'in:none,nudity,violence,sensitive'],
        ];
    }
}
