<?php

namespace Pinpoint\test;

trait Proxied_TestTrait
{
    function getReturnType()
    {
        echo "1";
    }
    function getReturnDescription()
    {
        echo "2";
    }
}