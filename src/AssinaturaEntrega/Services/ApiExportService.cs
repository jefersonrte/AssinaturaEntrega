using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using AssinaturaEntrega.Models;

namespace AssinaturaEntrega.Services;

public sealed class ExportResult
{
    public bool Success { get; init; }
    public int StatusCode { get; init; }
    public string? Message { get; init; }
}

/// <summary>
/// Envia a captura para o sistema web via multipart/form-data.
///
/// Contrato (POST {BaseUrl}{UploadPath}):
///   Campos de texto: orderNumber, recipientName, notes, deviceId, capturedAtUtc, id
///   Arquivos:        signature (image/png)  e, se habilitado, original (image/png)
///   Header opcional: Authorization: Bearer {AuthToken}
///   Sucesso:         qualquer status 2xx
/// </summary>
public sealed class ApiExportService
{
    private readonly ApiSettings _settings;
    private readonly HttpClient _http;

    public ApiExportService(ApiSettings settings)
    {
        _settings = settings;
        _http = new HttpClient
        {
            Timeout = TimeSpan.FromSeconds(settings.TimeoutSeconds <= 0 ? 30 : settings.TimeoutSeconds)
        };
    }

    public bool IsConfigured =>
        _settings.Enabled &&
        !string.IsNullOrWhiteSpace(_settings.BaseUrl);

    public async Task<ExportResult> SendAsync(DeliveryRecord record, CancellationToken ct = default)
    {
        if (!IsConfigured)
            return new ExportResult { Success = false, Message = "API não configurada (BaseUrl vazio ou desabilitada)." };

        string url = _settings.BaseUrl.TrimEnd('/') + "/" + _settings.UploadPath.TrimStart('/');

        try
        {
            using var content = new MultipartFormDataContent();
            content.Add(new StringContent(record.Id), "id");
            content.Add(new StringContent(record.OrderNumber), "orderNumber");
            content.Add(new StringContent(record.RecipientName), "recipientName");
            content.Add(new StringContent(record.Notes), "notes");
            content.Add(new StringContent(record.DeviceId), "deviceId");
            content.Add(new StringContent(record.CapturedAtUtc.ToString("o")), "capturedAtUtc");

            AddFile(content, "signature", record.SignatureImagePath);
            if (_settings.SendOriginal)
                AddFile(content, "original", record.OriginalImagePath);

            using var request = new HttpRequestMessage(HttpMethod.Post, url) { Content = content };
            if (!string.IsNullOrWhiteSpace(_settings.AuthToken))
                request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", _settings.AuthToken);

            using var response = await _http.SendAsync(request, ct).ConfigureAwait(false);
            int code = (int)response.StatusCode;

            if (response.IsSuccessStatusCode)
                return new ExportResult { Success = true, StatusCode = code, Message = "Enviado." };

            string body = await SafeReadBody(response, ct).ConfigureAwait(false);
            return new ExportResult
            {
                Success = false,
                StatusCode = code,
                Message = $"HTTP {code}. {body}"
            };
        }
        catch (TaskCanceledException)
        {
            return new ExportResult { Success = false, Message = "Tempo limite excedido ao enviar para o servidor." };
        }
        catch (Exception ex)
        {
            return new ExportResult { Success = false, Message = ex.Message };
        }
    }

    private static void AddFile(MultipartFormDataContent content, string field, string? path)
    {
        if (string.IsNullOrEmpty(path) || !File.Exists(path))
            return;

        var bytes = File.ReadAllBytes(path);
        var fileContent = new ByteArrayContent(bytes);
        fileContent.Headers.ContentType = new MediaTypeHeaderValue("image/png");
        content.Add(fileContent, field, Path.GetFileName(path));
    }

    private static async Task<string> SafeReadBody(HttpResponseMessage response, CancellationToken ct)
    {
        try
        {
            var text = await response.Content.ReadAsStringAsync(ct).ConfigureAwait(false);
            return text.Length > 300 ? text[..300] : text;
        }
        catch
        {
            return string.Empty;
        }
    }
}
