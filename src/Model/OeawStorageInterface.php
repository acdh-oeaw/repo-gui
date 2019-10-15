<?php

namespace Drupal\oeaw\Model;

interface OeawStorageInterface
{
    public function getACDHIdByPid(string $pid): array;
    public function getUUIDBySpecialIdentifier(string $identifier): array;
    public function getACDHTypes(bool $count = false, bool $searchBox = false) :array;
    public function getResourceTitle(string $uri): array;
    public function getTitleByIdentifier(string $string): array;
    public function getTitleByIdentifierArray(array $data, bool $dissemination = false): array;
    public function getTitleAndBasicInfoByIdentifier(string $data, bool $dissemination = false, string $lang = "en"): array;
    public function getTitleAndBasicInfoByIdentifierArray(array $data, bool $dissemination = false, string $lang = "en"): array;
    public function getValueByUriProperty(string $uri, string $property): array;
    public function runUserSparql(string $string): array;
    
    //child data sql
    public function getSpecialChildrenViewData(string $uri, string $limit, string $offset, bool $count = false, array $property): array;
    public function getSpecialDetailViewData(string $uri, string $limit, string $offset, bool $count = false, array $property): array ;
    
    //search related sql
    public function getAllPropertyForSearch():array;
    public function getClassesForSideBar():array;
    public function getDateForSearch(): array;
    
    
    //the children view SQLs
    public function getChildResourcesByProperty(string $uri, string $limit, string $offset, bool $count, array $property): array;
    public function getChildrenViewData(array $ids, string $limit, string $offset, bool $count = false): array;
    
    //API sql
    public function getTypeByIdentifier(string $identifier, string $lang = "en"): array;
    
    //detail view sql
    public function getInverseViewDataByIdentifier(array $data): array;
    public function getInverseViewDataByURL(string $url): array;
    public function getIsMembers(string $uri): array;
    public function getMetaInverseData(string $uri): array;
    public function getPropDataToExpertTable(array $data): array;
    public function getPropertyValueByUri(string $uri, string $property): string;
    public function getResourceModifyDateById(string $uuid): string;
    
    //cache sql
    public function getOntologyForCache(string $lang = "en"): array;
    //breadcrumb
    public function createBreadcrumbData(string $identifier, string $lang = "en"): array;
    
    
    //BLocks
    public function getMimeTypes(): array;
    
    //general
    public function getDataByProp(string $property, string $value, int $limit = 0, int $offset = 0, bool $count = false): array;
    public function getImageByIdentifier(string $string): string;
    public function getImage(string $value, string $property = null): string;
}
