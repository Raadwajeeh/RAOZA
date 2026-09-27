<?php
namespace App\Domain\Commerce\Enums;
enum CartStatus: string { case Active = 'active'; case Converted = 'converted'; case Abandoned = 'abandoned'; }
