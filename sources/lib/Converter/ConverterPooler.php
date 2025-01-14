<?php

namespace PommProject\ModelManager\Converter;

use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Converter\ConverterHolder;
use PommProject\Foundation\Converter\ConverterPooler as BaseConverterPooler;
use PommProject\Foundation\Exception\ConverterException;

class ConverterPooler extends BaseConverterPooler
{
    public function __construct(ConverterHolder $converterHolder)
    {
        parent::__construct($converterHolder);
    }

    public function createClient(string $identifier): ConverterClient
    {
        // if converter is not intializes
        if (!$this->converterHolder->hasType($identifier)) {
            // try to intialize it with the matching model
            try {
                $this->getSession()->getModel($identifier . 'Model');
            } catch (\Exception) {
                throw new ConverterException(sprintf("No converter registered for type '%s'.", $identifier));
            }
        }

        return new ConverterClient($identifier, $this->converterHolder->getConverterForType($identifier));
    }
}
