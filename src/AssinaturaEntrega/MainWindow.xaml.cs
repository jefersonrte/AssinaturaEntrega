using System.Diagnostics;
using System.IO;
using System.Windows;
using System.Windows.Input;
using System.Windows.Media.Imaging;
using AssinaturaEntrega.Models;
using AssinaturaEntrega.Services;
using OpenCvSharp;
using OpenCvSharp.WpfExtensions;

namespace AssinaturaEntrega;

public partial class MainWindow : Window
{
    private readonly ConfigService _config;
    private readonly AppSettings _settings;
    private readonly CameraService _camera;
    private readonly SignatureDetector _detector;
    private readonly ArchiveService _archive;
    private readonly ApiExportService _api;

    private bool _reviewing;              // true = frame congelado em revisão
    private Mat? _capturedOriginal;       // frame capturado (BGR)
    private Mat? _capturedCrop;           // recorte da assinatura (BGR)

    public MainWindow(ConfigService config, AppSettings settings)
    {
        InitializeComponent();

        _config = config;
        _settings = settings;
        _camera = new CameraService(settings.Camera);
        _detector = new SignatureDetector(settings.SignatureDetection);
        _archive = new ArchiveService(settings.Archive);
        _api = new ApiExportService(settings.Api);

        _camera.FrameReady += OnFrameReady;
        _camera.Error += OnCameraError;

        PreviewKeyDown += OnKeyDown;
    }

    // ---------- Ciclo de vida ----------

    private void Window_Loaded(object sender, RoutedEventArgs e)
    {
        _camera.Start();
        if (_camera.IsRunning)
            CameraHint.Visibility = Visibility.Collapsed;
        UpdatePendingCount();
    }

    private void Window_Closing(object sender, System.ComponentModel.CancelEventArgs e)
    {
        _camera.Dispose();
        DisposeCaptured();
    }

    private void OnKeyDown(object sender, KeyEventArgs e)
    {
        if (e.Key == Key.Space && CaptureButton.IsEnabled)
        {
            Capture_Click(sender, e);
            e.Handled = true;
        }
    }

    // ---------- Câmera ----------

    private void OnFrameReady(BitmapSource src)
    {
        if (_reviewing) return;
        Dispatcher.BeginInvoke(() =>
        {
            if (!_reviewing)
                PreviewImage.Source = src;
        });
    }

    private void OnCameraError(string message)
    {
        Dispatcher.BeginInvoke(() =>
        {
            CameraHint.Visibility = Visibility.Visible;
            CameraHint.Text = message;
            SetStatus(message);
        });
    }

    // ---------- Captura / detecção ----------

    private void Capture_Click(object sender, RoutedEventArgs e)
    {
        var frame = _camera.GrabFrame();
        if (frame is null || frame.Empty())
        {
            SetStatus("Sem imagem da câmera para capturar.");
            return;
        }

        DisposeCaptured();
        _capturedOriginal = frame;
        _reviewing = true;

        // Mostra o frame congelado.
        var frozen = frame.ToBitmapSource();
        frozen.Freeze();
        PreviewImage.Source = frozen;

        // Detecta a assinatura.
        if (_settings.SignatureDetection.Enabled)
        {
            var result = _detector.Detect(frame);
            if (result is { Found: true, Crop: not null })
            {
                _capturedCrop = result.Crop;
                var cropBmp = _capturedCrop.ToBitmapSource();
                cropBmp.Freeze();
                SignaturePreview.Source = cropBmp;
                DetectionInfo.Text =
                    $"Região detectada: {result.Region.Width}x{result.Region.Height}px. " +
                    "Confira e ajuste os dados.";
            }
            else
            {
                _capturedCrop = frame.Clone(); // fallback: usa o frame inteiro
                var cropBmp = _capturedCrop.ToBitmapSource();
                cropBmp.Freeze();
                SignaturePreview.Source = cropBmp;
                DetectionInfo.Text = "Assinatura não detectada automaticamente — será usado o frame inteiro.";
            }
        }
        else
        {
            _capturedCrop = frame.Clone();
            SignaturePreview.Source = frozen;
            DetectionInfo.Text = "Detecção desativada — usando o frame inteiro.";
        }

        SetReviewMode(true);
        SetStatus("Frame capturado. Preencha os dados e salve.");
    }

    private void NewCapture_Click(object sender, RoutedEventArgs e)
    {
        _reviewing = false;
        DisposeCaptured();
        SignaturePreview.Source = null;
        DetectionInfo.Text = "Capture um frame para detectar a assinatura.";
        SetReviewMode(false);
        SetStatus("Pronto.");
    }

    // ---------- Salvar / enviar ----------

    private async void SaveSend_Click(object sender, RoutedEventArgs e)
    {
        var record = SaveLocally();
        if (record is null) return;

        if (!_api.IsConfigured)
        {
            SetStatus("Salvo localmente. API não configurada — veja appsettings.json.");
            UpdatePendingCount();
            NewCapture_Click(sender, e);
            return;
        }

        SetBusy(true);
        SetStatus("Enviando para o sistema web...");
        var result = await _api.SendAsync(record);

        record.Sent = result.Success;
        record.SentAtUtc = result.Success ? DateTime.UtcNow : null;
        record.LastError = result.Success ? null : result.Message;
        _archive.WriteMetadata(record);

        SetStatus(result.Success
            ? "Enviado com sucesso ao sistema web."
            : "Salvo localmente, mas o envio falhou: " + result.Message + " (ficará pendente para reenvio).");

        SetBusy(false);
        UpdatePendingCount();
        NewCapture_Click(sender, e);
    }

    private void SaveLocal_Click(object sender, RoutedEventArgs e)
    {
        var record = SaveLocally();
        if (record is null) return;
        SetStatus("Salvo localmente em: " + record.ArchiveFolder);
        UpdatePendingCount();
        NewCapture_Click(sender, e);
    }

    private DeliveryRecord? SaveLocally()
    {
        if (_capturedOriginal is null)
        {
            SetStatus("Nada para salvar — capture um frame primeiro.");
            return null;
        }

        var record = new DeliveryRecord
        {
            OrderNumber = OrderBox.Text.Trim(),
            RecipientName = RecipientBox.Text.Trim(),
            Notes = NotesBox.Text.Trim(),
            DeviceId = _settings.Device.DeviceId,
            CapturedAtUtc = DateTime.UtcNow
        };

        try
        {
            _archive.Save(record, _capturedCrop, _capturedOriginal);
            OrderBox.Clear();
            RecipientBox.Clear();
            NotesBox.Clear();
            return record;
        }
        catch (Exception ex)
        {
            MessageBox.Show("Falha ao salvar localmente:\n\n" + ex.Message, "Erro",
                MessageBoxButton.OK, MessageBoxImage.Error);
            return null;
        }
    }

    private async void ResendPending_Click(object sender, RoutedEventArgs e)
    {
        if (!_api.IsConfigured)
        {
            MessageBox.Show("API não está configurada (veja appsettings.json).", "Reenvio",
                MessageBoxButton.OK, MessageBoxImage.Information);
            return;
        }

        var pending = _archive.LoadPending();
        if (pending.Count == 0)
        {
            SetStatus("Nenhum registro pendente.");
            return;
        }

        SetBusy(true);
        int ok = 0, fail = 0;
        foreach (var rec in pending)
        {
            SetStatus($"Reenviando {ok + fail + 1}/{pending.Count}...");
            var result = await _api.SendAsync(rec);
            rec.Sent = result.Success;
            rec.SentAtUtc = result.Success ? DateTime.UtcNow : null;
            rec.LastError = result.Success ? null : result.Message;
            _archive.WriteMetadata(rec);
            if (result.Success) ok++; else fail++;
        }

        SetBusy(false);
        SetStatus($"Reenvio concluído: {ok} enviados, {fail} com falha.");
        UpdatePendingCount();
    }

    // ---------- Menu ----------

    private void OpenArchiveFolder_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            Directory.CreateDirectory(_archive.RootPath);
            Process.Start(new ProcessStartInfo("explorer.exe", $"\"{_archive.RootPath}\"") { UseShellExecute = true });
        }
        catch (Exception ex)
        {
            SetStatus("Não foi possível abrir a pasta: " + ex.Message);
        }
    }

    private void OpenConfig_Click(object sender, RoutedEventArgs e)
    {
        try
        {
            Process.Start(new ProcessStartInfo("notepad.exe", $"\"{_config.ConfigPath}\"") { UseShellExecute = true });
            SetStatus("Edite o appsettings.json e reinicie o aplicativo para aplicar.");
        }
        catch (Exception ex)
        {
            SetStatus("Não foi possível abrir as configurações: " + ex.Message);
        }
    }

    private void Exit_Click(object sender, RoutedEventArgs e) => Close();

    // ---------- Auxiliares de UI ----------

    private void SetReviewMode(bool reviewing)
    {
        CaptureButton.IsEnabled = !reviewing;
        SaveSendButton.IsEnabled = reviewing;
        SaveLocalButton.IsEnabled = reviewing;
        NewCaptureButton.IsEnabled = reviewing;
    }

    private void SetBusy(bool busy)
    {
        SaveSendButton.IsEnabled = !busy && _reviewing;
        SaveLocalButton.IsEnabled = !busy && _reviewing;
        CaptureButton.IsEnabled = !busy && !_reviewing;
        Cursor = busy ? Cursors.Wait : Cursors.Arrow;
    }

    private void SetStatus(string text) => StatusText.Text = text;

    private void UpdatePendingCount()
    {
        try
        {
            int count = _archive.LoadPending().Count;
            PendingText.Text = count > 0 ? $"Pendentes de envio: {count}" : string.Empty;
        }
        catch
        {
            PendingText.Text = string.Empty;
        }
    }

    private void DisposeCaptured()
    {
        _capturedOriginal?.Dispose();
        _capturedOriginal = null;
        _capturedCrop?.Dispose();
        _capturedCrop = null;
    }
}
