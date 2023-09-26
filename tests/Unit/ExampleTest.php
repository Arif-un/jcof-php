<?php

use Jcof\Jcof;

function covertAndRevertJson($jsonString)
{
    $data = json_decode($jsonString, true);

    $jcofCompressed = Jcof::stringify($data);

    $jcofCompressedToArray = Jcof::parse($jcofCompressed);

    expect(($data))->toEqual(($jcofCompressedToArray));
}

test('example 1', function () {
    $jsonString = file_get_contents(__DIR__.'/../data/circuitsim.json');

    covertAndRevertJson($jsonString);
});

test('example 2', function () {
    $jsonString = file_get_contents(__DIR__.'/../data/madrid.json');

    covertAndRevertJson($jsonString);
});

test('example 3', function () {
    $jsonString = file_get_contents(__DIR__.'/../data/comets.json');

    covertAndRevertJson($jsonString);
});

test('example 4', function () {
    $jsonString = file_get_contents(__DIR__.'/../data/meteorites.json');

    covertAndRevertJson($jsonString);
});

test('example 5', function () {
    $jsonString = file_get_contents(__DIR__.'/../data/pokedex.json');

    covertAndRevertJson($jsonString);
});

test('example 6', function () {
    $jsonString = file_get_contents(__DIR__.'/../data/pokemon.json');

    covertAndRevertJson($jsonString);
});

test('example 7', function () {
    $jsonString = file_get_contents(__DIR__.'/../data/tiny.json');

    covertAndRevertJson($jsonString);
});
