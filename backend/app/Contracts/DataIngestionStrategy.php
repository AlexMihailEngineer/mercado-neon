<?php

namespace App\Contracts;

interface DataIngestionStrategy
{
    /**
     * Fetch and normalize data into a standard format.
     *
     * @return array
     */
    public function fetch(): array;
}
