<?php

namespace Drupal\oeaw\Model;

interface OeawCustomSparqlInterface
{
    public function getCollectionBinaries(string $url): string;
}
