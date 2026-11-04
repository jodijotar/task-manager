SET NAMES utf8mb4;
USE gerenciador;

-- Senhas: admin -> admin123 | maria -> 123456 | joao -> 123456
INSERT INTO usuarios (nome, sobrenome, data_nascimento, login, senha_hash, is_admin) VALUES
('Administrador', 'Sistema', '1990-01-01', 'admin', '$2b$10$AvHzjwmoTP85G2Jxa00U8.WWjLiBxzV/eeJ0YYXPJ7hDRYGy771uG', 1),
('Maria', 'Silva',  '1995-05-20', 'maria', '$2b$10$GYhHhmZxtoQWDFL0Pd1il.33YtMI8b6ycKxzSKRKcJJ4889kzTSjK', 0),
('João',  'Santos', '1998-11-02', 'joao',  '$2b$10$GYhHhmZxtoQWDFL0Pd1il.33YtMI8b6ycKxzSKRKcJJ4889kzTSjK', 0);

INSERT INTO tarefas (usuario_id, titulo, descricao, data_criacao, data_conclusao, status) VALUES
(2, 'Estudar PHP',          'Revisar PDO e JWT',             NOW() - INTERVAL 5 DAY, CURDATE() - INTERVAL 3 DAY, 'em_atraso'),
(2, 'Entregar relatório',   'Relatório final do projeto',    NOW() - INTERVAL 2 DAY, CURDATE() + INTERVAL 4 DAY, 'em_aberto'),
(2, 'Reunião com o grupo',  'Alinhar tarefas da semana',     NOW() - INTERVAL 6 DAY, CURDATE() - INTERVAL 1 DAY, 'concluida'),
(3, 'Comprar material',     NULL,                            NOW() - INTERVAL 1 DAY, CURDATE() + INTERVAL 2 DAY, 'em_aberto'),
(1, 'Revisar permissões',   'Conferir usuários cadastrados', NOW(),                  CURDATE() + INTERVAL 7 DAY, 'em_aberto');
