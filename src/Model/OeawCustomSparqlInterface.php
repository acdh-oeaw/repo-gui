<?php

namespace Drupal\oeaw\Model;

interface OeawCustomSparqlInterface
{
    public function createPersonsApiSparql(string $str): string;
    public function createBasicApiSparql(string $str, string $type): string;
    public function createFullTextSparql(array $data, string $limit, string $page, bool $count = false, string $order = "datedesc"): string;
    public function createPublicationsApiSparql(string $str): string;
    public function getCollectionBinaries(string $url): string;
    public function createGNDPersonsApiSparql(): string;
}
