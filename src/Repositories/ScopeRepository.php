<?php namespace Limoncello\Passport\Repositories;

/**
 * Copyright 2015-2017 info@neomerx.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use DateTimeImmutable;
use Limoncello\Passport\Contracts\Entities\ScopeInterface;
use Limoncello\Passport\Contracts\Repositories\ScopeRepositoryInterface;

/**
 * @package Limoncello\Passport
 */
abstract class ScopeRepository extends BaseRepository implements ScopeRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function index(): array
    {
        return parent::indexResources();
    }

    /**
     * @inheritdoc
     */
    public function create(ScopeInterface $scope): ScopeInterface
    {
        $now    = new DateTimeImmutable();
        $schema = $this->getDatabaseSchema();
        $this->createResource([
            $schema->getScopesIdentityColumn()    => $scope->getIdentifier(),
            $schema->getScopesDescriptionColumn() => $scope->getDescription(),
            $schema->getScopesCreatedAtColumn()   => $now,
        ]);

        $scope->setCreatedAt($now);

        return $scope;
    }

    /**
     * @inheritdoc
     */
    public function read(string $identifier): ScopeInterface
    {
        return $this->readResource($identifier);
    }

    /**
     * @inheritdoc
     */
    public function update(ScopeInterface $scope): void
    {
        $now    = new DateTimeImmutable();
        $schema = $this->getDatabaseSchema();
        $this->updateResource($scope->getIdentifier(), [
            $schema->getScopesDescriptionColumn() => $scope->getDescription(),
            $schema->getScopesUpdatedAtColumn()   => $now,
        ]);
        $scope->setUpdatedAt($now);
    }

    /**
     * @inheritdoc
     */
    public function delete(string $identifier): void
    {
        $this->deleteResource($identifier);
    }

    /**
     * @inheritdoc
     */
    protected function getTableNameForWriting(): string
    {
        return $this->getDatabaseSchema()->getScopesTable();
    }

    /**
     * @inheritdoc
     */
    protected function getPrimaryKeyName(): string
    {
        return $this->getDatabaseSchema()->getScopesIdentityColumn();
    }
}
