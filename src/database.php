<?php

namespace Dipcb05\RestifyPhp;

use PDO;
use MongoDB\Client as MongoClient;
use Dotenv\Dotenv;

class Database
{
    private ?PDO $pdo = null;
    private ?MongoClient $mongo = null;
    private ?string $dbType;
    private ?string $dbName;
    
    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../config');
        $dotenv->load();

        $this->dbType = getenv('DB_TYPE');
        $this->dbName = getenv('DB_NAME');

        switch (strtolower($this->dbType)) {
            case 'mysql':
            case 'pgsql':
            case 'sqlite':
                $this->connectSQL();
                break;
            case 'mongodb':
                $this->connectMongoDB();
                break;
            default:
                throw new \Exception("Unsupported database type: {$this->dbType}");
        }
    }

    private function connectSQL(): void
    {
        $dsn = match ($this->dbType) {
            'mysql' => "mysql:host=" . getenv('DB_HOST') . ";dbname=" . $this->dbName,
            'pgsql' => "pgsql:host=" . getenv('DB_HOST') . ";dbname=" . $this->dbName,
            'sqlite' => "sqlite:" . getenv('DB_PATH'),
            default => throw new \Exception("Invalid SQL database type")
        };
        
        $this->pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'));
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function connectMongoDB(): void
    {
        $this->mongo = new MongoClient(getenv('DB_URI'));
    }

    public function query(string $sql, array $params = []): array
    {
        if (!$this->pdo) {
            throw new \Exception("SQL database connection is not initialized");
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): bool
    {
        if (!$this->pdo) {
            throw new \Exception("SQL database connection is not initialized");
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function getMongoCollection(string $collection)
    {
        if (!$this->mongo) {
            throw new \Exception("MongoDB connection is not initialized");
        }
        return $this->mongo->selectDatabase($this->dbName)->selectCollection($collection);
    }
}
