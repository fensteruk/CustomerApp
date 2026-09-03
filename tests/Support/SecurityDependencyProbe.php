<?php

namespace Tests\Support;

use Livewire\Component;

class SecurityDependencyProbe extends Component
{
    public string $message = 'Security probe';

    public function render(): string
    {
        return '<div>{{ $message }}</div>';
    }
}
