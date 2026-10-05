<?php

declare(strict_types=1);

namespace App\Entity;

enum TaskStatus: string
{
    case Pending = 'pendente';
    case Doing = 'em_andamento';
    case Done = 'concluida';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Doing => 'Em andamento',
            self::Done => 'Concluída',
        };
    }
}
