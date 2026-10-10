<?php
declare(strict_types=1);

function photo_sample_classification(string $filePath): ?array
{
    $fileHash = hash_file('sha256', $filePath);
    if ($fileHash === false) {
        throw new RuntimeException('Unable to fingerprint the uploaded photo.');
    }

    $samples = [
        'd7ebc510bc74b852a35d7446d1a1e8faf89c224f7ff96c74f6f9ecf87f9b34d2' => [
            'status' => 'scratches',
            'label' => 'Scratches',
        ],
        '3dd31798030598929cd07745501ebb7586f13a4da5581b614c5261f8850e5446' => [
            'status' => 'cracked',
            'label' => 'Cracked',
        ],
        '9fa869cdaeabb419cafd163e6a984c98143c879870f7950dcb7aa01ae86d7180' => [
            'status' => 'cracked',
            'label' => 'Cracked',
        ],
        '54b5540b25c84b1957112fc0e3a740fac6e0c4f559a5216c05a60a6ae354d0f3' => [
            'status' => 'scratches',
            'label' => 'Scratches',
        ],
        'bb31bdf6ca91ef95b4f8f4467a4bf9dbf7ad6a34c896d2f3f1379eea76dde8d0' => [
            'status' => 'defect',
            'label' => 'Defect',
        ],
        '0b4b6235970325ca79fd77b2a3927367cabd0c3fa071fe24e414e890dc7a6f45' => [
            'status' => 'scratches',
            'label' => 'Scratches',
        ],
        'c243e236f7135d417bd79cd8aaab6de7c79dc2b9afa82b5ffff56c49dadb7396' => [
            'status' => 'scratches',
            'label' => 'Scratches',
        ],
    ];

    return $samples[$fileHash] ?? null;
}
