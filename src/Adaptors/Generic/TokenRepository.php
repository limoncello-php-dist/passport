<?php namespace Limoncello\Passport\Adaptors\Generic;

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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Limoncello\Passport\Contracts\Entities\DatabaseSchemaInterface;
use Limoncello\Passport\Contracts\Entities\TokenInterface;
use PDO;

/**
 * @package Limoncello\Passport
 */
class TokenRepository extends \Limoncello\Passport\Repositories\TokenRepository
{
    /**
     * @var string
     */
    private $modelClass;

    /**
     * @param Connection              $connection
     * @param DatabaseSchemaInterface $databaseSchema
     * @param string                  $modelClass
     */
    public function __construct(
        Connection $connection,
        DatabaseSchemaInterface $databaseSchema,
        string $modelClass = Token::class
    ) {
        $this->setConnection($connection)->setDatabaseSchema($databaseSchema);
        $this->modelClass = $modelClass;
    }

    /**
     * @inheritdoc
     */
    public function read(int $identifier): ?TokenInterface
    {
        $token = parent::read($identifier);

        if ($token !== null) {
            $this->addScope($token);
        }

        return $token;
    }

    /**
     * @inheritdoc
     */
    public function readByCode(string $code, int $expirationInSeconds): ?TokenInterface
    {
        $token = parent::readByCode($code, $expirationInSeconds);
        if ($token !== null) {
            $this->addScope($token);
        }

        return $token;
    }

    /**
     * @inheritdoc
     */
    public function readByValue(string $tokenValue, int $expirationInSeconds): ?TokenInterface
    {
        $token = parent::readByValue($tokenValue, $expirationInSeconds);
        if ($token !== null) {
            $this->addScope($token);
        }

        return $token;
    }

    /**
     * @inheritdoc
     */
    public function readByRefresh(string $refreshValue, int $expirationInSeconds): ?TokenInterface
    {
        $token = parent::readByRefresh($refreshValue, $expirationInSeconds);
        if ($token !== null) {
            $this->addScope($token);
        }

        return $token;
    }

    /**
     * @inheritdoc
     */
    public function readByUser(int $userId, int $expirationInSeconds, int $limit = null): array
    {
        /** @var TokenInterface[] $tokens */
        $tokens = parent::readByUser($userId, $expirationInSeconds, $limit);

        // select scope identifiers for tokens
        if (empty($tokens) === false) {
            $schema        = $this->getDatabaseSchema();
            $tokenIdColumn = $schema->getTokensScopesTokenIdentityColumn();
            $scopeIdColumn = $schema->getTokensScopesScopeIdentityColumn();

            $connection = $this->getConnection();
            $query      = $connection->createQueryBuilder();

            $tokenIds = array_keys($tokens);
            $query
                ->select([$tokenIdColumn, $scopeIdColumn])
                ->from($schema->getTokensScopesTable())
                ->where($query->expr()->in($tokenIdColumn, $tokenIds))
                ->orderBy($tokenIdColumn);

            $statement = $query->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $tokenScopePairs = $statement->fetchAll();

            $curTokenId = null;
            $curScopes  = null;
            // set selected scopes to tokens
            foreach ($tokenScopePairs as $pair) {
                $tokenId = $pair[$tokenIdColumn];
                $scopeId = $pair[$scopeIdColumn];

                if ($curTokenId !== $tokenId) {
                    $assignScopes = $curTokenId !== null && empty($curScopes) === false;
                    $assignScopes ? $tokens[$curTokenId]->setScopeIdentifiers($curScopes) : null;
                    $curTokenId = $tokenId;
                    $curScopes  = [$scopeId];

                    continue;
                }

                $curScopes[] = $scopeId;
            }
            $curTokenId === null || empty($curScopes) === true ?: $tokens[$curTokenId]->setScopeIdentifiers($curScopes);
        }

        return $tokens;
    }

    /**
     * @inheritdoc
     */
    public function readPassport(string $tokenValue, int $expirationInSeconds): ?array
    {
        $statement = $this->createPassportDataQuery($tokenValue, $expirationInSeconds)->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $data = $statement->fetch();
        $result = null;
        if ($data !== false) {
            $schema  = $this->getDatabaseSchema();
            $tokenId = $data[$schema->getTokensIdentityColumn()];
            $scopes  =  $this->readScopeIdentifiers($tokenId);
            $data[$schema->getTokensViewScopesColumn()] = $scopes;
            $result = $data;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function getClassName(): string
    {
        return $this->modelClass;
    }

    /**
     * @inheritdoc
     */
    protected function getTableNameForReading(): string
    {
        return $this->getTableNameForWriting();
    }

    /**
     * @param string $tokenValue
     * @param int    $expirationInSeconds
     *
     * @return QueryBuilder
     */
    private function createPassportDataQuery(
        string $tokenValue,
        int $expirationInSeconds
    ): QueryBuilder {
        $schema = $this->getDatabaseSchema();
        $query  = $this->createEnabledTokenByColumnWithExpirationCheckQuery(
            $tokenValue,
            $schema->getTokensValueColumn(),
            $expirationInSeconds,
            $schema->getTokensValueCreatedAtColumn()
        );

        $tokensTable = $this->getTableNameForReading();
        $usersTable  = $aliased = $schema->getUsersTable();
        $usersFk     = $schema->getTokensUserIdentityColumn();
        $usersPk     = $schema->getUsersIdentityColumn();
        $query->innerJoin(
            $tokensTable,
            $usersTable,
            $aliased,
            "`$tokensTable`.`$usersFk` = `$aliased`.`$usersPk`"
        );

        return $query;
    }

    /**
     * @param TokenInterface $token
     *
     * @return void
     */
    private function addScope(TokenInterface $token)
    {
        $token->setScopeIdentifiers($this->readScopeIdentifiers($token->getIdentifier()));
    }
}
