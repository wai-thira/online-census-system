$(document).ready(function() {
    
    function initSlideshow() {
        const $slides = $('.slide');
        let currentSlide = 0;
        const slideInterval = 5000; 
        
        function showSlide(index) {
            $slides.removeClass('active');
            $slides.eq(index).addClass('active');
        }
        
        function nextSlide() {
            currentSlide = (currentSlide + 1) % $slides.length;
            showSlide(currentSlide);
        }
        
        showSlide(currentSlide);
        setInterval(nextSlide, slideInterval);
        
        $('.slideshow-container').hover(
            function() {
            },
            function() {
                
            }
        );
    }
    
    function preloadImages() {
        const imageUrls = [
            'images/slide1.jpg',
            'images/slide2.jpg', 
            'images/slide3.jpg',
            'images/slide4.jpg',
            'images/slide5.jpg'
        ];
        
        $.each(imageUrls, function(index, url) {
            $('<img/>')[0].src = url;
        });
    }

    function initSmoothScroll() {
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top
                }, 800);
            }
        });
    }
    
    function initScrollAnimations() {
        const $featureCards = $('.feature-card');
        
        $(window).on('scroll', function() {
            const windowHeight = $(window).height();
            const windowTop = $(window).scrollTop();
            
            $featureCards.each(function() {
                const $card = $(this);
                const cardTop = $card.offset().top;
                
                if (cardTop < windowTop + windowHeight - 100) {
                    $card.addClass('animate-in');
                }
            });
        });
    }
    
    function initLoading() {
        $('body').addClass('loading');
        
        $(window).on('load', function() {
            setTimeout(function() {
                $('body').removeClass('loading');
                $('.container').addClass('loaded');
            }, 500);
        });
    }
 
    function initButtonAnimations() {
        $('.btn').on('click', function() {
            const $btn = $(this);
            $btn.addClass('clicked');
            setTimeout(function() {
                $btn.removeClass('clicked');
            }, 300);
        });
    }
  
    preloadImages();
    initSlideshow();
    initSmoothScroll();
    initScrollAnimations();
    initLoading();
    initButtonAnimations();
    
    console.log('NPR Kenya website initialized successfully');
});