<?php

test('it builds metadata for remote urls without filesystem stat calls', function () {
    $metadata = getFileMetadata('https://bibliamundi.com/wp-content/uploads/2023/09/Portugues-NVI-All-Bible.pdf');

    expect($metadata['exists'])->toBeTrue();
    expect($metadata['origin'])->toBe('url');
    expect($metadata['mime_type'])->toBe('application/pdf');
    expect($metadata['size'])->toBe(0);
    expect($metadata['name'])->toBe('Portugues-NVI-All-Bible.pdf');
    expect($metadata['path'])->toBe('https://bibliamundi.com/wp-content/uploads/2023/09/Portugues-NVI-All-Bible.pdf');
});
