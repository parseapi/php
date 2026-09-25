<?php

// Deliberately weak typing: numeric inputs must reach the Card guard unchanged.
function callCardFromWeakPhp(\ParseAPI\Client $client, $input): array
{
	return $client->card($input);
}
