<?php
namespace Webelop\AlbumBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webelop\AlbumBundle\Entity\Picture;
use Webelop\AlbumBundle\Entity\Tag;

/**
 * @method findOneByHash(string $hash): Picture|null
 */
class PictureRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Picture::class);
    }

    public function findByTag(Tag $tag)
    {
        return $this->createQueryBuilder('p')
                ->select('p')
                ->innerJoin('p.tags', 't')
                ->where('t.id = :tag')
                ->orderBy('p.originalDate', $tag->getSort())
                ->getQuery()
                ->setParameter(':tag', $tag->getId())
            ->getResult();
    }

    public function findTagged(int $limit, int $offset, bool $random)
    {
        $sort = $random ? ['RAND()' => 'ASC'] : ['p.original_date' => 'DESC'];
        return $this->createQueryBuilder('p')
            ->select('p')
            ->innerJoin('p.tags', 't')
            ->orderBy(key($sort), current($sort))
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function remove(Picture $picture)
    {
        $this->getEntityManager()->remove($picture);
        $this->getEntityManager()->flush();
    }
}
