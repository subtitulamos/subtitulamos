<?php

// Configuration file for Doctrine's console cmd
require 'app/bootstrap.php';

return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($entityManager);