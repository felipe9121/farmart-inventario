-- Login del servidor para la app. Si ya existe se sincroniza la clave con el .env.
IF SUSER_ID(N'$(APP_USER)') IS NULL
    CREATE LOGIN [$(APP_USER)] WITH PASSWORD = N'$(APP_PASSWORD)', CHECK_POLICY = ON;
ELSE
    ALTER LOGIN [$(APP_USER)] WITH PASSWORD = N'$(APP_PASSWORD)';
GO
