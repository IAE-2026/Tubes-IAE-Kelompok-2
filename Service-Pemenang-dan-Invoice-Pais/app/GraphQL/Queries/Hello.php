<?php

namespace App\GraphQL\Queries;

class Hello
{
    public function __invoke($_, array $args)
    {
        return [
            'name' => 'Fariz Shadiq',
            'nim' => '102022430010',
            'message' => 'EAI MENYENANGKAN!'
        ];
    }
}