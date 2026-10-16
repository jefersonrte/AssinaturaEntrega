namespace AssinaturaEntrega.Models;

/// <summary>
/// Representa uma captura de assinatura de entrega e seus metadados.
/// Serializado em metadata.json dentro da pasta de arquivo.
/// </summary>
public class DeliveryRecord
{
    public string Id { get; set; } = Guid.NewGuid().ToString("N");
    public string OrderNumber { get; set; } = "";
    public string RecipientName { get; set; } = "";
    public string Notes { get; set; } = "";
    public string DeviceId { get; set; } = "";
    public DateTime CapturedAtUtc { get; set; } = DateTime.UtcNow;

    /// <summary>Caminho local da assinatura recortada.</summary>
    public string? SignatureImagePath { get; set; }
    /// <summary>Caminho local da imagem original (frame completo).</summary>
    public string? OriginalImagePath { get; set; }
    /// <summary>Pasta onde o registro foi arquivado.</summary>
    public string? ArchiveFolder { get; set; }

    public bool Sent { get; set; }
    public DateTime? SentAtUtc { get; set; }
    public string? LastError { get; set; }
}
