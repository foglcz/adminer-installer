<?php
namespace foglcz\Adminer;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * The Adminer Editor installer
 *
 * @link https://github.com/foglcz/adminer-installer
 * @author Pavel Ptacek <birdie at animalgroup dot cz>
 * @licence LGPL-2.0
 */
class Installer extends \Composer\Installer\LibraryInstaller {

    /**
     * Installs binaries for adminer where it's needed most
     *
     * @param \Composer\Package\PackageInterface $package
     */
    protected function installBinaries(PackageInterface $package) {
        /** @var $this->io \Composer\IO\IOInterface */
        // Check for common document_root directories -> array(zend, symfony, nette)
        $common = array('public', 'web', 'www');
        $default = null;
        foreach($common as $one) {
            $default = $one;
            if(file_exists($one) && is_dir($one)) {
                break;
            }
        }

        // Ask for install configuration
        $this->binDir = $this->io->ask('Adminer | Please supply public html folder [' . $default . ']: ', $default);
        $adminerFile = $this->io->ask('Adminer | Please supply adminer install file relative to the public html [adminer/index.php]: ', 'adminer/index.php');

        // Get current dir, go to adminer source & compile
        $this->io->write('Adminer | Compiling..', false);
        $currentDir = getcwd();
        chdir($this->getInstallPath($package));

        // pre-install step: create files for include(), which will do nothing (and will recover by default)
        touch($this->getInstallPath($package) . '/externals/JsShrink/jsShrink.php');

        // externals are not loaded by composer at the moment, issues include() errors - mute them.
        shell_exec('php compile.php > compile.output.txt');
        if(!file_exists('adminer.php')) {
            $this->io->overwrite('Adminer | Compilation of Adminer failed!', false);
            chdir($currentDir);
            return;
        }

        // Change to current dir, sanitize the bindir
        chdir($currentDir);
        $this->io->overwrite('Adminer | Moving to installation dir', false);
        $this->filesystem->ensureDirectoryExists($this->binDir . '/' . dirname($adminerFile));
        if(file_exists($this->binDir . '/' . $adminerFile)) { // if the directory exists, check for presence of index.php (& check whether it's adminer.)
            @unlink($this->binDir . '/' . $adminerFile);
        }
        if(file_exists($this->binDir) . '/' . dirname($adminerFile) . '/adminer.css') {
            @unlink(file_exists($this->binDir) . '/' . dirname($adminerFile) . '/adminer.css');
        }

        copy($this->getInstallPath($package) . '/adminer.php', $this->binDir . '/' . $adminerFile);
        @unlink($this->getInstallPath($package) . '/adminer.php');
        @unlink($this->getInstallPath($package) . '/editor.php');

        // Copy the style file
        $this->io->overwrite('Adminer | Downloading css file...', false);
        $rfs = new \Composer\Util\RemoteFilesystem($this->io);
        $rfs->copy('https://raw.github.com/nette/sandbox/master/www/adminer/adminer.css',
            'https://raw.github.com/nette/sandbox/master/www/adminer/adminer.css',
            $this->binDir . '/' . dirname($adminerFile) . '/adminer.css',
            false);

        // Done.
        $this->io->overwrite('Adminer | The installation has been successful.');
        if(basename($adminerFile) === 'index.php') {
            $this->io->write('Adminer | You can access adminer via http://<server>/' . dirname($adminerFile));
        }
        else {
            $this->io->write('Adminer | You can access adminer via http://<server>/' . $adminerFile);
        }

        $this->io->write('Adminer | If you are using IDEA clone editor (= Phpstorm etc.), mark ' . $this->binDir . '/' . $adminerFile . ' as plaintext to avoid performance issues.');
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType) {
        return 'vrana-adminer' === $packageType;
    }
}