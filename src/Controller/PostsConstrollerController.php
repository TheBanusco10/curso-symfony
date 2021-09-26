<?php

namespace App\Controller;

use App\Entity\Comentarios;
use App\Entity\Posts;
use App\Form\ComentariosType;
use App\Form\PostsType;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class PostsConstrollerController extends AbstractController
{
    /**
     * @Route("/nuevo-post", name="nuevo-post")
     */
    public function index(Request $request, SluggerInterface $slugger): Response
    {

        $post = new Posts();

        $form = $this->createForm(PostsType::class, $post);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {

            $user = $this->getUser();

            $post->setUser($user);

            $archivo = $form['foto']->getData();

            if ($archivo) {

                $originalFilename = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$archivo->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $archivo->move(
                        $this->getParameter('brochures_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    throw new Exception('Ha ocurrido un error al subir el archivo');
                }

                $post->setFoto($newFilename);
            }

            $entityManager = $this->getDoctrine()->getManager();

            $entityManager->persist($post);
            $entityManager->flush();

            $this->addFlash('exito', 'Nueva entrada creada');

            return $this->redirectToRoute('dashboard');
        }

        return $this->render('posts/index.html.twig', [
            'controller_name' => 'PostsConstrollerController',
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/post/{id}", name="post")
     */
    public function verPost(Request $request, $id) {
        $em = $this->getDoctrine()->getManager();

        $post = $em->getRepository(Posts::class)->find($id);
        $comentarios = $em->getRepository(Comentarios::class)->getComentariosPost($id)->getResult();

        $comentario = new Comentarios();

        $form = $this->createForm(ComentariosType::class, $comentario);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $user = $this->getUser();
            $comentario->setUser($user);
            $comentario->setPosts($post);

            $em->persist($comentario);
            $em->flush();

            return $this->redirectToRoute('post',['id'=>$post->getId()]);
        }

        return $this->render('posts/verPost.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
            'comentarios' => $comentarios
        ]);
    }

    /**
     * @Route("/misPosts", name="misPosts")
     */
    public function misPosts() {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        $posts = $em->getRepository(Posts::class)->findBy(['user'=>$user]);

        return $this->render('posts/misPosts.html.twig', [
            'posts' => $posts
        ]);
    }

    /**
     * @Route("/api/likes", name="likes", options={"expose"=true})
     */
    public function like(Request $request) {
        // Si es una petición AJAX
        if($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $user = $this->getUser();

            $id = $request->request->get('id');

            $post = $em->getRepository(Posts::class)->find($id);

            $likes = $post->getLikes();

            $likes .= $user->getId() . ',';
            $post->setLikes($likes);

            $em->flush();

            return $this->json(['likes' => $likes]);

        }else {
            throw new Exception('Error en la petición');
        }
    }
}
