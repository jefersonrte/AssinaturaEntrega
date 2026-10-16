using System.Windows;
using System.Windows.Threading;
using AssinaturaEntrega.Services;

namespace AssinaturaEntrega;

public partial class App : Application
{
    protected override void OnStartup(StartupEventArgs e)
    {
        base.OnStartup(e);

        DispatcherUnhandledException += OnUnhandledException;

        var config = new ConfigService();
        var settings = config.Load();
        var window = new MainWindow(config, settings);
        window.Show();
    }

    private void OnUnhandledException(object sender, DispatcherUnhandledExceptionEventArgs e)
    {
        MessageBox.Show(
            "Ocorreu um erro inesperado:\n\n" + e.Exception.Message,
            "Erro",
            MessageBoxButton.OK,
            MessageBoxImage.Error);
        e.Handled = true;
    }
}
