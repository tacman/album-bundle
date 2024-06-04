<?php
declare(strict_types=1);

namespace Webelop\AlbumBundle\Service;

use DirectoryIterator;
use DateTime;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Webelop\AlbumBundle\Entity;

/**
 * Folder provides directory listing features
 */
class FolderManager
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface;
     */
    private $em;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $folderRepo;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $pictureRepo;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @param array                  $parameters
     * @param EntityManagerInterface $em
     */
    public function __construct(array $parameters, EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->parameters = $parameters;

        $this->folderRepo = $this->em->getRepository(Entity\Folder::class);
        $this->pictureRepo = $this->em->getRepository(Entity\Picture::class);
    }

    public function findOneFolderByPath($path)
    {
        //Find a folder entity in db
        $folder = $this->folderRepo->findOneByPath($path);

        if (!$folder) {
            $class = $this->folderRepo->getClassName();
            $folder = new $class;
            $folder->setName(basename($path));
            $folder->setPath($path);

            $this->em->persist($folder);
        }

        return $folder;
    }

    public function listMediaFiles(Entity\Folder $folder): array
    {
        //Get all it's images and create a hashmap for matching (img > img path)
        $root = $this->parameters['album_root'];
        if (!is_dir($root)) {
            throw new \UnexpectedValueException(
                'Invalid Webelop\AlbumBundle configuration: album_root must be an existing directory. '.$root
            );
        }

        $all = $pictures = $saved = array();
        foreach ($folder->getPictures() as $picture) {
            $saved[$picture->getHash()] = $picture;
        }

        foreach (new DirectoryIterator($root . '/' . $folder->getPath()) as $file) {
            $hash = substr(
                md5(implode('/', [
                    $this->parameters['salt'],
                    $folder->getPath(),
                    $file->getFilename()
                ])),
                0,
                12
            );
            $all[] = array(
                'filename' => $file->getFilename(),
                'pathname' => $file->getPathname(),
                'hash' => $hash,
            );
        }

        usort($all, function ($a, $b) {
            return strcmp($a['filename'], $b['filename']);
        });

        foreach ($all as $file) {
            // Exclude hidden folders
            if (strpos($file['filename'], '.') === 0) {
                continue;
            }

            // Filter valid extensions

            // TODO: Extract all this logic to file handlers, allow to extend with custom handlers
            $extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            if ('jpg' === $extension || 'jpeg' === $extension || 'png' === $extension || 'heic' === $extension) {
                // Nothing to do
            } elseif ('mp4' === $extension || 'mov' === $extension || 'mkv' === $extension || 'avi' === $extension) {
                // Check previews
            } else {
                continue;
            }

            $hash = $file['hash'];
            if (isset($saved[$hash])) {
                $picture = $saved[$hash];
                unset($saved[$hash]);
            } else {
                $picture = new Entity\Picture;
                $picture->setPath($file['filename']);
                $picture->setHash($hash);
                $picture->setFolder($folder);

                $date = null;
                $exif = @exif_read_data($file['pathname']);
                if (!empty($exif['FileDateTime'])) {
                    $date = new DateTime("@" . $exif['FileDateTime']);
                } else {
                    $output = array();
                    exec(sprintf('exiftool %s', escapeshellarg($file['pathname'])), $output, $error);
                    foreach ($output ?: array() as $line) {
                        if (strpos($line, 'Date/Time Original') === false) {
                            continue;
                        }

                        list($key, $value) = preg_split('/\s+:\s+/', $line);
                        if ($key == 'Date/Time Original') {
                            $date = \DateTime::createFromFormat('Y:m:d h:i:s', $value);
                            break;
                        }
                    }
                }

                if ($date) {
                    $picture->setOriginalDate($date);
                }

                $this->em->persist($picture);
            }

            $pictures[] = $picture;
        }

        //Remove inexistant pictures
        foreach ($saved as $picture) {
//            $this->em->remove($picture);
        }

        $this->em->flush();

        //Sort pictures by date shot (EXIF)
        usort($pictures, function ($a, $b) {
            if (!$a->getOriginalDate() || !$b->getOriginalDate()) {
                return strcmp($a->getPath(), $b->getPath());
            }
            return $a->getOriginalDate() > $b->getOriginalDate();
        });

        return $pictures;
    }

    public function listFolders(?string $path): array
    {
        $root = $this->parameters['album_root'];

        $absFolders = $folders = array();
        $p = '';

        if ($path && (0 !== strpos($path, '.')) && is_dir("$root/$path")) {
            foreach (array_filter(explode('/', trim($path, '/'))) as $i => $level) {
                $folders[] = array(
                    'path' => $p .= $level,
                    'name' => $level,
                    'depth' => $i + 1
                );
                $p .= '/';
            }
        } else {
            $path = '';
        }

        if (is_dir($root . '/' . $path)) {
            foreach (new DirectoryIterator($root . '/' . $path) as $fileInfo) {
                if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                    $absFolders[] = $fileInfo->getPathname();
                }
            }
        }

        sort($absFolders, SORT_DESC);

        foreach ($absFolders as $folder) {
            if (strpos(basename($folder), '.') === 0) {
                continue;
            }
            $folders[] = array(
                'path' => $short = ltrim(str_replace($root, '', $folder), '/'),
                'name' => basename($folder),
                'depth' => count(explode('/', $short))
            );
        }

        return $folders;
    }
}
