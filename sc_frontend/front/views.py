import os
from django.contrib.auth.decorators import login_required
from django.http import HttpResponse, Http404
from django.shortcuts import render_to_response, get_object_or_404
from django.template import RequestContext
from django.core.exceptions import ObjectDoesNotExist
from django.utils.encoding import smart_str
from front.models import Camera, Image
from sc_frontend import settings


@login_required
def index(request):
    cameras = Camera.objects.filter(user=request.user)
    context = RequestContext(request, {
        'cameras': cameras,
    })
    return render_to_response('front/index.html', context)


@login_required
def latest_image(request, camera_id):
    camera = get_object_or_404(Camera, id=camera_id, user=request.user)
    try:
        image = Image.objects.filter(camera=camera).latest('received')
    except ObjectDoesNotExist:
        raise Http404("Image not found.")

    filename = '%04d-%s.jpg' % (int(camera_id), image.received.strftime("%Y%m%d-%H%M%S"))
    filepath = os.path.join(settings.IMAGE_DIR, filename)
    response = HttpResponse(mimetype='image/jpeg')
    response['X-Sendfile'] = smart_str(filepath)
    return response


def dummy(request):
    context = RequestContext(request)
    return render_to_response('front/base.html', context)
