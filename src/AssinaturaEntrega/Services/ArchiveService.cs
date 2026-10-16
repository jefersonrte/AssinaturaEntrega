using System.IO;
using System.Text.Json;
using AssinaturaEntrega.Models;
using OpenCvSharp;

namespace AssinaturaEntrega.Services;

/// <summary>
/// Arquiva cada captura em uma pasta organizada por data/pedido e grava
/// um metadata.json. Estrutura:
///   {RootPath}/{yyyy}/{MM}/{yyyy-MM-dd}/{pedido}_{timestamp}/
///       original.png, signature.png, metadata.json
/// </summary>
public sealed class ArchiveService
{
    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };

    private readonly ArchiveSettings _settings;

    public string RootPath { get; }

    public ArchiveService(ArchiveSettings settings)
    {
        _settings = settings;
        RootPath = ConfigService.ExpandPath(settings.RootPath);
    }

    /// <summary>
    /// Grava os arquivos e o metadata. Atualiza os caminhos no record.
    /// Retorna a pasta criada.
    /// </summary>
    public string Save(DeliveryRecord record, Mat? signatureCrop, Mat? original)
    {
        var local = record.CapturedAtUtc.ToLocalTime();
        string safeOrder = Sanitize(string.IsNullOrWhiteSpace(record.OrderNumber)
            ? "sem-pedido"
            : record.OrderNumber);

        string folder = Path.Combine(
            RootPath,
            local.ToString("yyyy"),
            local.ToString("MM"),
            local.ToString("yyyy-MM-dd"),
            $"{safeOrder}_{local:HHmmss}_{record.Id[..6]}");

        Directory.CreateDirectory(folder);

        if (_settings.SaveSignatureCrop && signatureCrop is { Empty: false })
        {
            string path = Path.Combine(folder, "signature.png");
            WritePng(path, signatureCrop);
            record.SignatureImagePath = path;
        }

        if (_settings.SaveOriginal && original is { Empty: false })
        {
            string path = Path.Combine(folder, "original.png");
            WritePng(path, original);
            record.OriginalImagePath = path;
        }

        record.ArchiveFolder = folder;
        WriteMetadata(record);
        return folder;
    }

    public void WriteMetadata(DeliveryRecord record)
    {
        if (string.IsNullOrEmpty(record.ArchiveFolder)) return;
        string metaPath = Path.Combine(record.ArchiveFolder, "metadata.json");
        File.WriteAllText(metaPath, JsonSerializer.Serialize(record, JsonOptions));
    }

    /// <summary>Lê todos os registros ainda não enviados (para reenvio).</summary>
    public List<DeliveryRecord> LoadPending()
    {
        var result = new List<DeliveryRecord>();
        if (!Directory.Exists(RootPath)) return result;

        foreach (var meta in Directory.EnumerateFiles(RootPath, "metadata.json", SearchOption.AllDirectories))
        {
            try
            {
                var rec = JsonSerializer.Deserialize<DeliveryRecord>(File.ReadAllText(meta));
                if (rec is { Sent: false })
                    result.Add(rec);
            }
            catch
            {
                // metadata corrompido: ignora.
            }
        }

        return result.OrderBy(r => r.CapturedAtUtc).ToList();
    }

    /// <summary>
    /// Grava PNG via ImEncode + File.WriteAllBytes para evitar problemas do
    /// OpenCV com caminhos contendo acentos/Unicode no Windows.
    /// </summary>
    private static void WritePng(string path, Mat image)
    {
        Cv2.ImEncode(".png", image, out byte[] buffer);
        File.WriteAllBytes(path, buffer);
    }

    private static string Sanitize(string value)
    {
        foreach (var c in Path.GetInvalidFileNameChars())
            value = value.Replace(c, '-');
        return value.Trim();
    }
}
