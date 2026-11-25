<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $guarded = [];

    protected $hidden = ['provisional_monthly_price'];

    protected function casts(): array
    {
        return [
            'provisional_monthly_price' => 'decimal:2',
            'is_active' => 'boolean',
            'listed_publicly' => 'boolean',
            'monthly_page_quota' => 'integer',
            'trial_page_allowance' => 'integer',
            'topup_packs' => 'array',
        ];
    }

    /** @return array<int, array{pages: int, price: string, label: string}> */
    public function packs(): array
    {
        return array_values(array_map(function ($pack) {
            return [
                'pages' => (int) ($pack['pages'] ?? 0),
                'price' => number_format((float) ($pack['price'] ?? 0), 2, '.', ''),
                'label' => (string) ($pack['label'] ?? ''),
            ];
        }, $this->topup_packs ?? []));
    }
}
