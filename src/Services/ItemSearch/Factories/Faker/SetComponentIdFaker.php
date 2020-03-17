<?php

namespace IO\Services\ItemSearch\Factories\Faker;

class SetComponentIdFaker extends AbstractFaker
{
    public function fill($data)
    {
        if(is_array($data) && count($data) > 0) {
            return $data;
        }

        return [
            $this->number()
        ];
    }
}