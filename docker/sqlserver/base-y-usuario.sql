-- Crea la base (si no existe) y el usuario de la app con permisos mínimos:
-- leer y escribir datos, sin DDL (no puede crear/alterar tablas ni desactivar triggers).
-- Los DENY por tabla (kardex, bitácora, documentos) los aplica una migración.
IF DB_ID(N'$(DB)') IS NULL
    CREATE DATABASE [$(DB)];
GO
USE [$(DB)];
GO
IF DATABASE_PRINCIPAL_ID(N'$(APP_USER)') IS NULL
    CREATE USER [$(APP_USER)] FOR LOGIN [$(APP_USER)];
ALTER ROLE db_datareader ADD MEMBER [$(APP_USER)];
ALTER ROLE db_datawriter ADD MEMBER [$(APP_USER)];
GO
