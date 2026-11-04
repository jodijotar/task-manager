# Dicionário de dados

## Modelo conceitual
- Um **usuário** possui **0..N tarefas**.
- Uma **tarefa** pertence a **exatamente 1 usuário** (exclusão em cascata).

## usuarios
| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | Identificador |
| nome | VARCHAR(80) | NOT NULL | Nome |
| sobrenome | VARCHAR(80) | NOT NULL | Sobrenome |
| data_nascimento | DATE | NOT NULL | Data de nascimento |
| login | VARCHAR(50) | NOT NULL, UNIQUE | Login de acesso |
| senha_hash | VARCHAR(255) | NOT NULL | Hash bcrypt (`password_hash`) |
| is_admin | TINYINT(1) | NOT NULL, DEFAULT 0 | 1 = pode autorizar novos cadastros |
| criado_em | DATETIME | NOT NULL, DEFAULT now | Data do cadastro |

## tarefas
| Coluna | Tipo | Restrições | Descrição |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | Identificador |
| usuario_id | INT UNSIGNED | NOT NULL, FK → usuarios.id (CASCADE) | Dono da tarefa |
| titulo | VARCHAR(150) | NOT NULL | Título |
| descricao | TEXT | NULL | Descrição |
| data_criacao | DATETIME | NOT NULL, DEFAULT now | Criação (automática) |
| data_conclusao | DATE | NOT NULL | Prazo de conclusão |
| status | ENUM | NOT NULL, DEFAULT 'em_aberto' | `em_aberto`, `concluida`, `em_atraso` |

**Regra de status:** `em_atraso` é calculado pelo backend: tarefa não concluída com `data_conclusao` anterior a hoje.

Índices: `uk_usuarios_login (login)`, `idx_tarefas_usuario_status (usuario_id, status)`.
