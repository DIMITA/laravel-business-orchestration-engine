<?php

namespace Dimita\BusinessOrchestration\Contracts;

interface Executable
{
    public function execute(array $payload);
}