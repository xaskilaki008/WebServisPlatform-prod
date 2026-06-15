# Актуальная ERD структуры БД

Файл фиксирует текущую структуру после перехода к единой авторизации через `users` + `roles`. Legacy-таблицы `administrators`, `beach_operators` и `beach_operator_logs` пока оставлены для совместимости существующих маршрутов и журналов операторских корректировок.

```mermaid
erDiagram
    roles ||--o{ users : "назначается"
    users ||--o{ user_action_logs : "совершает"
    users ||--o| operators : "имеет профиль оператора"
    users ||--o{ email_verification_codes : "получает код"

    operators ||--o{ operator_beach : "закрепляется"
    beaches ||--o{ operator_beach : "имеет операторов"

    beach_operators ||--o{ beach_operator_logs : "создаёт legacy-записи"
    beaches ||--o{ beach_operator_logs : "имеет legacy-записи"
    beaches ||--o{ beach_operators : "имеет legacy-операторов"

    beaches ||--o{ wave_forecasts : "имеет прогнозы"
    visitors ||--o{ reactions : "оставляет"
    beaches ||--o{ reactions : "получает реакции"
    visitors ||--o{ favorite_beaches : "сохраняет"
    beaches ||--o{ favorite_beaches : "сохраняется"

    roles {
        bigint id PK
        string name UK
        string description
    }

    users {
        bigint id PK
        bigint role_id FK
        string name
        string email UK
        string login UK
        string nickname_key UK
        string password
        string password_hash
        string full_name
        string last_name
        string first_name
        string middle_name
        boolean is_active
        datetime email_verified_at
        datetime created_at
        datetime updated_at
    }

    email_verification_codes {
        bigint id PK
        bigint user_id FK
        string code_hash
        datetime expires_at
        datetime used_at
        int attempts
        datetime created_at
    }

    user_action_logs {
        bigint id PK
        bigint user_id FK
        string action
        string entity_type
        bigint entity_id
        text description
        string ip_address
        string user_agent
        datetime created_at
    }

    operators {
        bigint id PK
        bigint user_id FK
        string work_phone
        datetime created_at
    }

    operator_beach {
        bigint id PK
        bigint operator_id FK
        bigint beach_id FK
        datetime created_at
    }

    beaches {
        bigint id PK
        string name
        decimal latitude
        decimal longitude
        int number
        int wave_level
        string operator_status
        string operator_warning
        string operator_wave_direction
        int operator_wave_azimuth
        int operator_wave_period
        string operator_access_status
        datetime operator_updated_at
        datetime operator_expires_at
        decimal fetch_latitude
        decimal fetch_longitude
        datetime created_at
        datetime updated_at
    }

    wave_forecasts {
        bigint id PK
        bigint beach_id FK
        decimal wave_height
        decimal wave_direction
        decimal wave_period
        decimal water_temperature
        decimal air_temperature
        datetime forecast_time
        datetime model_run_at
        int model_run_hour
        int forecast_hour
        datetime parsed_at
        text source_files
        datetime created_at
        datetime updated_at
    }

    visitors {
        bigint id PK
        string visitor_hash UK
        string cookie_hash
        string nickname_key UK
        string last_name
        string first_name
        string middle_name
        datetime created_at
        datetime updated_at
    }

    reactions {
        bigint id PK
        bigint beach_id FK
        bigint visitor_id FK
        string reaction_type
        datetime created_at
        datetime updated_at
    }

    favorite_beaches {
        bigint id PK
        bigint visitor_id FK
        bigint beach_id FK
        datetime created_at
    }

    beach_operators {
        bigint id PK
        bigint beach_id FK
        string login UK
        string password
        string operator_hash UK
        string name
        string last_name
        string first_name
        string middle_name
        string work_phone
        datetime created_at
        datetime updated_at
    }

    beach_operator_logs {
        bigint id PK
        bigint beach_operator_id FK
        bigint beach_id FK
        datetime submitted_at
        datetime expires_at
        string operator_status
        string operator_warning
        string operator_wave_direction
        int operator_wave_azimuth
        int operator_wave_period
        string operator_access_status
        datetime created_at
        datetime updated_at
    }

    administrators {
        bigint id PK
        string login UK
        string password
        string name
        datetime created_at
        datetime updated_at
    }
```
