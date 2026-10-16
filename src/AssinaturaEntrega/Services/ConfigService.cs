using System.IO;
using System.Text.Json;
using AssinaturaEntrega.Models;

namespace AssinaturaEntrega.Services;

/// <summary>
/// Carrega/salva o appsettings.json que fica ao lado do executável.
/// </summary>
public class ConfigService
{
    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        PropertyNameCaseInsensitive = true,
        WriteIndented = true
    };

    public string ConfigPath { get; }

    /// <summary>Pasta de configuração usada pelo instalador (gravável por todos os usuários).</summary>
    public static string ProgramDataDir =>
        Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "AssinaturaEntrega");

    public ConfigService()
    {
        // Em publish single-file, AppContext.BaseDirectory é a pasta temporária de
        // extração; a pasta real do .exe vem de Environment.ProcessPath.
        string exeDir = Path.GetDirectoryName(Environment.ProcessPath) ?? AppContext.BaseDirectory;

        // Instalado (Program Files): o instalador grava a config em
        // %ProgramData%\AssinaturaEntrega\appsettings.json. Se existir, ela manda.
        // Senão (versão portátil / desenvolvimento), usa o arquivo ao lado do .exe.
        string instalado = Path.Combine(ProgramDataDir, "appsettings.json");
        ConfigPath = File.Exists(instalado) ? instalado : Path.Combine(exeDir, "appsettings.json");
    }

    public AppSettings Load()
    {
        try
        {
            if (File.Exists(ConfigPath))
            {
                var json = File.ReadAllText(ConfigPath);
                var settings = JsonSerializer.Deserialize<AppSettings>(json, JsonOptions);
                if (settings != null)
                    return settings;
            }
        }
        catch
        {
            // Config inválido: cai no padrão para não travar o app.
        }

        return new AppSettings();
    }

    public void Save(AppSettings settings)
    {
        var json = JsonSerializer.Serialize(settings, JsonOptions);
        File.WriteAllText(ConfigPath, json);
    }

    /// <summary>Expande variáveis como %USERPROFILE% em caminhos.</summary>
    public static string ExpandPath(string path)
        => Environment.ExpandEnvironmentVariables(path ?? string.Empty);
}
