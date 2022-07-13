<?php

declare(strict_types=1);

namespace SionModel\Db\Model;

use Exception;
use InvalidArgumentException;
use Laminas\Db\Adapter\AdapterInterface;
use Psr\Container\ContainerInterface;

use function file_exists;
use function filesize;
use function move_uploaded_file;
use function sha1_file;
use function substr;

class FilesTable extends SionTable
{
    public function getFiles(): array
    {
        if (null !== ($cache = $this->sionCacheService->fetchCachedEntityObjects('files'))) {
            return $cache;
        }

        $sql = "SELECT `FileId`, `StoreFileName`, `OriginalFileName`, `FileKind`, `Description`,
`Size`, `Sha1`, `ContentTags`, `StructureTags`, `MimeType`, `IsPublic`, `IsEncrypted`,
`EncryptedEncryptionKey`, `UpdatedOn`, `UpdatedBy`, `CreatedOn`, `CreatedBy`
FROM `files` WHERE 1";

        $results  = $this->fetchSome(null, $sql, null);
        $entities = [];
        $config   = $this->getSionModelConfig();
        foreach ($results as $row) {
            $id       = $this->filterDbId($row['FileId']);
            $isPublic = $this->filterDbBool($row['IsPublic']);
            if ($isPublic) {
                $path = $config['public_file_directory'];
            } else {
                $path = $config['file_directory'];
            }
            if (!str_ends_with($path, '/')) {
                $path .= '/';
            }
            $storeFileName = $this->filterDbString($row['StoreFileName']);
            $path         .= $storeFileName;
            $entities[$id] = [
                'fileId'                 => $id,
                'storeFileName'          => $storeFileName,
                'originalFileName'       => $this->filterDbString($row['OriginalFileName']),
                'fileKind'               => $this->filterDbString($row['FileKind']),
                'description'            => $this->filterDbString($row['Description']),
                'size'                   => $this->filterDbInt($row['Size']),
                'sha1'                   => $this->filterDbString($row['Sha1']),
                'contentTags'            => $this->filterDbArray($row['ContentTags']),
                'structureTags'          => $this->filterDbArray($row['StructureTags']),
                'mimeType'               => $this->filterDbString($row['MimeType']),
                'isPublic'               => $isPublic,
                'isEncrypted'            => $this->filterDbBool($row['IsEncrypted']),
                'encryptedEncryptionKey' => $this->filterDbString($row['EncryptedEncryptionKey']),
                'createdOn'              => $this->filterDbDate($row['CreatedOn']),
                'createdBy'              => $this->filterDbId($row['CreatedBy']),
                'updatedOn'              => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'              => $this->filterDbId($row['UpdatedBy']),
            ];
        }

        $this->sionCacheService->cacheEntityObjects('files', $entities, ['file']);
        return $entities;
    }

    /**
     * @param int $id
     * @return mixed[]
     */
    public function getFile($id)
    {
        $entities = $this->getFiles();

        if (! isset($entities[$id]) || ! ($entity = $entities[$id])) {
            return null;
        }

        return $entity;
    }

    public function preprocessFile($data, $entityData, $action)
    {
        if ($action !== 'create') {
            return $data;
        }
        if (! isset($data['originalFileName'])) {
            throw new InvalidArgumentException('originalFileName key required');
        }
        $filePath = 'tmp/' . $data['originalFileName'];
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException('File not found');
        }
        $data['sha1'] = sha1_file($filePath);
        if (false === $data['size'] = filesize($filePath)) {
            throw new Exception('Error determining the size of the file');
        }

        if (isset($data['isEncrypted']) && $data['isEncrypted']) {
            //@todo add encryption support
        }

        //move file to permanent store
        $config   = $this->getSionModelConfig();
        $isPublic = isset($data['isPublic']) ? (bool) $data['isPublic'] : false;
        $newPath  = '';
        if ($isPublic) {
            $newPath = $config['public_file_directory'];
        } else {
            $newPath = $config['file_directory'];
        }
        if ('/' !== substr($newPath, -1)) {
            $newPath .= '/';
        }
        $extension             = 'jpg';
        $storeFileName         = $data['sha1'] . $extension;
        $data['storeFileName'] = $storeFileName;
        $newPath              .= $storeFileName;

        //This could lead to multiple file records referring to 1 file. That's OK
        if (! file_exists($newPath)) {
            if (false === move_uploaded_file($data['originalFileName'], $newPath)) {
                throw new Exception('Error processing file');
            }
        }

        return $data;
    }

    /**
     * Get the sionModelConfig value
     */
    public function getSionModelConfig(): array
    {
        return $this->generalConfig['sion_model'];
    }
}
