<?php

namespace Importer\Contracts;

interface EventDataService {
    public function search($filter,int $page = 1);
}