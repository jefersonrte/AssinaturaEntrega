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

    public ConfigService()
    {
        ConfigPath = Path.Combine(AppContext.BaseDirectory, "appsettings.json");
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
